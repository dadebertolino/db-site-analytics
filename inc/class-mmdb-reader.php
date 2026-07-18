<?php
/**
 * DBSA_MMDB_Reader — Lettore minimale del formato MaxMind DB (.mmdb).
 *
 * Implementazione pura PHP, senza dipendenze Composer, ottimizzata per
 * lookup country (DB-IP Country Lite / GeoLite2 Country). Read-only.
 *
 * Specifica formato: https://maxmind.github.io/MaxMind-DB/
 *
 * @package DB_Site_Analytics
 * @since 3.2.0
 */

if (!defined('ABSPATH')) exit;

class DBSA_MMDB_Reader {

    const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

    /** @var resource */
    private $handle;

    /** @var array Metadati del database */
    private $metadata = array();

    /** @var int Dimensione in byte di un nodo dell'albero */
    private $node_bytes;

    /** @var int Offset assoluto inizio sezione dati (dopo separatore 16 byte) */
    private $data_start;

    /** @var int Dimensione albero di ricerca in byte */
    private $tree_size;

    /**
     * @throws RuntimeException se il file non è un MMDB valido.
     */
    public function __construct(string $filepath) {
        if (!is_readable($filepath)) {
            throw new RuntimeException('MMDB non leggibile: ' . esc_html($filepath));
        }

        $this->handle = fopen($filepath, 'rb');
        if (!$this->handle) {
            throw new RuntimeException('Impossibile aprire MMDB.');
        }

        $this->read_metadata($filepath);

        $record_size      = (int) $this->metadata['record_size']; // bit
        $node_count       = (int) $this->metadata['node_count'];
        $this->node_bytes = (int) ($record_size * 2 / 8);
        $this->tree_size  = $node_count * $this->node_bytes;
        $this->data_start = $this->tree_size + 16; // separatore 16 byte di zeri
    }

    public function __destruct() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Lookup di un IP (v4 o v6). Ritorna l'array dati decodificato o null.
     */
    public function lookup(string $ip): ?array {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        // DB IPv6: gli IPv4 sono mappati sui primi 96 bit a zero
        if ((int) $this->metadata['ip_version'] === 6 && strlen($packed) === 4) {
            $packed = str_repeat("\x00", 12) . $packed;
        } elseif ((int) $this->metadata['ip_version'] === 4 && strlen($packed) === 16) {
            return null; // IPv6 su DB solo-IPv4
        }

        $node_count = (int) $this->metadata['node_count'];
        $bit_count  = strlen($packed) * 8;
        $node       = 0;

        for ($i = 0; $i < $bit_count; $i++) {
            if ($node >= $node_count) {
                break;
            }
            $byte = ord($packed[$i >> 3]);
            $bit  = ($byte >> (7 - ($i % 8))) & 1;
            $node = $this->read_node_record($node, $bit);
        }

        if ($node === $node_count) {
            return null; // nessun dato per questo IP
        }
        if ($node < $node_count) {
            return null; // albero malformato
        }

        // Offset nella sezione dati (il valore include il separatore)
        $offset = $this->tree_size + ($node - $node_count);
        list($data, ) = $this->decode($offset);

        return is_array($data) ? $data : null;
    }

    /**
     * Scorciatoia: codice ISO 3166-1 alpha-2 del paese, o '' se assente.
     */
    public function country_code(string $ip): string {
        $data = $this->lookup($ip);
        return strtoupper((string) ($data['country']['iso_code'] ?? ''));
    }

    // -------------------------------------------------------------------------
    // Albero di ricerca
    // -------------------------------------------------------------------------

    /**
     * Legge il record sinistro (bit=0) o destro (bit=1) di un nodo.
     */
    private function read_node_record(int $node, int $bit): int {
        $base = $node * $this->node_bytes;

        switch ((int) $this->metadata['record_size']) {
            case 24:
                $raw = $this->read($base + ($bit ? 3 : 0), 3);
                return (ord($raw[0]) << 16) | (ord($raw[1]) << 8) | ord($raw[2]);

            case 28:
                $raw = $this->read($base, 7);
                if ($bit === 0) {
                    return ((ord($raw[3]) >> 4) << 24)
                        | (ord($raw[0]) << 16) | (ord($raw[1]) << 8) | ord($raw[2]);
                }
                return ((ord($raw[3]) & 0x0F) << 24)
                    | (ord($raw[4]) << 16) | (ord($raw[5]) << 8) | ord($raw[6]);

            case 32:
                $raw = $this->read($base + ($bit ? 4 : 0), 4);
                return (ord($raw[0]) << 24) | (ord($raw[1]) << 16)
                    | (ord($raw[2]) << 8) | ord($raw[3]);
        }

        throw new RuntimeException('Record size non supportato.');
    }

    // -------------------------------------------------------------------------
    // Decoder sezione dati
    // -------------------------------------------------------------------------

    /**
     * Decodifica il valore all'offset assoluto dato.
     * Ritorna array(valore, offset_successivo).
     */
    private function decode(int $offset): array {
        $ctrl   = ord($this->read($offset, 1));
        $offset++;

        $type = $ctrl >> 5;

        // Pointer (tipo 1): risolvi e decodifica al target
        if ($type === 1) {
            $ss      = ($ctrl >> 3) & 0x03;
            $vv      = $ctrl & 0x07;
            $sizes   = array(1, 2, 3, 4);
            $raw     = $this->read($offset, $sizes[$ss]);
            $offset += $sizes[$ss];

            $ptr     = 0;
            $raw_len = strlen($raw);
            for ($i = 0; $i < $raw_len; $i++) {
                $ptr = ($ptr << 8) | ord($raw[$i]);
            }
            switch ($ss) {
                case 0: $ptr = ($vv << 8)  | $ptr; break;
                case 1: $ptr = (($vv << 16) | $ptr) + 2048; break;
                case 2: $ptr = (($vv << 24) | $ptr) + 526336; break;
                // case 3: pointer = valore a 32 bit così com'è
            }

            list($value, ) = $this->decode($this->data_start + $ptr);
            return array($value, $offset);
        }

        // Tipo esteso (0): il tipo reale è nel byte successivo
        if ($type === 0) {
            $type = 7 + ord($this->read($offset, 1));
            $offset++;
        }

        // Dimensione payload
        $size = $ctrl & 0x1F;
        if ($size === 29) {
            $size = 29 + ord($this->read($offset, 1));
            $offset++;
        } elseif ($size === 30) {
            $raw    = $this->read($offset, 2);
            $size   = 285 + ((ord($raw[0]) << 8) | ord($raw[1]));
            $offset += 2;
        } elseif ($size === 31) {
            $raw    = $this->read($offset, 3);
            $size   = 65821 + ((ord($raw[0]) << 16) | (ord($raw[1]) << 8) | ord($raw[2]));
            $offset += 3;
        }

        switch ($type) {
            case 2: // UTF-8 string
            case 4: // bytes
                $value = $size > 0 ? $this->read($offset, $size) : '';
                return array($value, $offset + $size);

            case 3: // double (8 byte, big-endian)
                $value = unpack('E', $this->read($offset, 8))[1];
                return array($value, $offset + 8);

            case 15: // float (4 byte, big-endian)
                $value = unpack('G', $this->read($offset, 4))[1];
                return array($value, $offset + 4);

            case 5:  // uint16
            case 6:  // uint32
            case 8:  // int32
            case 9:  // uint64
            case 10: // uint128 (troncato a int PHP: mai usato per country)
                $value = 0;
                if ($size > 0) {
                    $raw = $this->read($offset, $size);
                    for ($i = 0; $i < $size; $i++) {
                        $value = ($value << 8) | ord($raw[$i]);
                    }
                }
                return array($value, $offset + $size);

            case 7: // map
                $map = array();
                for ($i = 0; $i < $size; $i++) {
                    list($key, $offset) = $this->decode($offset);
                    list($val, $offset) = $this->decode($offset);
                    $map[$key] = $val;
                }
                return array($map, $offset);

            case 11: // array
                $arr = array();
                for ($i = 0; $i < $size; $i++) {
                    list($val, $offset) = $this->decode($offset);
                    $arr[] = $val;
                }
                return array($arr, $offset);

            case 14: // boolean (size è il valore, nessun payload)
                return array($size === 1, $offset);

            default:
                throw new RuntimeException('Tipo MMDB non supportato: ' . esc_html((string) $type));
        }
    }

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    private function read_metadata(string $filepath): void {
        $filesize = filesize($filepath);
        $chunk    = min($filesize, 131072); // i metadati sono negli ultimi 128KB

        fseek($this->handle, $filesize - $chunk);
        $tail = fread($this->handle, $chunk);

        $pos = strrpos($tail, self::METADATA_MARKER);
        if ($pos === false) {
            throw new RuntimeException('Marker metadata MMDB non trovato.');
        }

        $meta_offset = ($filesize - $chunk) + $pos + strlen(self::METADATA_MARKER);

        // I metadati sono una map: per decodificarla serve data_start=0
        // (i pointer nei metadati sono relativi all'inizio dei metadati stessi)
        $this->data_start = $meta_offset;
        list($this->metadata, ) = $this->decode($meta_offset);

        foreach (array('record_size', 'node_count', 'ip_version') as $key) {
            if (!isset($this->metadata[$key])) {
                throw new RuntimeException('Metadata MMDB incompleti.');
            }
        }
    }

    /**
     * Info database per la UI (tipo, data build, versione IP).
     */
    public function get_metadata(): array {
        return array(
            'database_type' => (string) ($this->metadata['database_type'] ?? ''),
            'build_epoch'   => (int) ($this->metadata['build_epoch'] ?? 0),
            'ip_version'    => (int) ($this->metadata['ip_version'] ?? 0),
            'node_count'    => (int) ($this->metadata['node_count'] ?? 0),
        );
    }

    // -------------------------------------------------------------------------
    // I/O
    // -------------------------------------------------------------------------

    private function read(int $offset, int $length): string {
        fseek($this->handle, $offset);
        $data = fread($this->handle, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new RuntimeException('Lettura MMDB fuori dai limiti.');
        }
        return $data;
    }
}
