<?php

namespace App\Services;

use RuntimeException;

class BackupArchiveEncryption
{
    private const MAGIC = "TECKFO-BACKUP-ENC-1\n";

    private const CIPHER = 'aes-256-gcm';

    private const CHUNK_SIZE = 1048576;

    public function assertConfigured(): void
    {
        if (! function_exists('openssl_encrypt')
            || ! in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('OpenSSL AES-256-GCM diperlukan untuk enkripsi salinan off-site.');
        }
        $this->key();
    }

    public function encryptFile(string $sourcePath, string $destinationPath): void
    {
        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new RuntimeException('Arsip sumber tidak dapat dibuka untuk enkripsi.');
        }

        $destination = fopen($destinationPath, 'wb');
        if ($destination === false) {
            fclose($source);
            throw new RuntimeException('File sementara terenkripsi tidak dapat dibuat.');
        }

        try {
            $key = $this->key();
            $this->writeAll($destination, self::MAGIC);
            $chunkIndex = 0;
            while (! feof($source)) {
                $plaintext = fread($source, self::CHUNK_SIZE);
                if ($plaintext === false) {
                    throw new RuntimeException('Gagal membaca arsip untuk dienkripsi.');
                }
                if ($plaintext === '') {
                    continue;
                }

                $nonce = random_bytes(12);
                $tag = '';
                $ciphertext = openssl_encrypt(
                    $plaintext,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    $this->associatedData($chunkIndex),
                    16,
                );
                if ($ciphertext === false || strlen($tag) !== 16) {
                    throw new RuntimeException('Enkripsi arsip gagal.');
                }

                $this->writeAll($destination, pack('N', strlen($plaintext)).$nonce.$tag.$ciphertext);
                $chunkIndex++;
            }

            $this->writeAll($destination, pack('N', 0));
            if (! fflush($destination)) {
                throw new RuntimeException('File terenkripsi gagal ditulis sepenuhnya.');
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    public function decryptStream($source, string $destinationPath): void
    {
        if (! is_resource($source)) {
            throw new RuntimeException('Stream sumber arsip terenkripsi tidak valid.');
        }

        $destination = fopen($destinationPath, 'xb');
        if ($destination === false) {
            throw new RuntimeException('File hasil dekripsi sudah ada atau tidak dapat dibuat.');
        }

        try {
            $key = $this->key();
            if ($this->readExact($source, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('Format arsip terenkripsi tidak dikenali.');
            }

            $chunkIndex = 0;
            while (true) {
                $lengthBytes = $this->readExact($source, 4);
                $length = unpack('Nlength', $lengthBytes)['length'];
                if ($length === 0) {
                    $trailing = fread($source, 1);
                    if ($trailing === false || $trailing !== '') {
                        throw new RuntimeException('Arsip terenkripsi memiliki data tambahan yang tidak valid.');
                    }

                    break;
                }
                if ($length > self::CHUNK_SIZE) {
                    throw new RuntimeException('Ukuran blok arsip terenkripsi tidak valid.');
                }

                $nonce = $this->readExact($source, 12);
                $tag = $this->readExact($source, 16);
                $ciphertext = $this->readExact($source, $length);
                $plaintext = openssl_decrypt(
                    $ciphertext,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    $this->associatedData($chunkIndex),
                );
                if ($plaintext === false || strlen($plaintext) !== $length) {
                    throw new RuntimeException('Autentikasi arsip gagal; kunci salah atau berkas rusak.');
                }

                $this->writeAll($destination, $plaintext);
                $chunkIndex++;
            }

            if ($chunkIndex === 0) {
                throw new RuntimeException('Arsip terenkripsi tidak berisi data.');
            }
            if (! fflush($destination)) {
                throw new RuntimeException('File hasil dekripsi gagal ditulis sepenuhnya.');
            }
        } catch (\Throwable $exception) {
            fclose($destination);
            if (is_file($destinationPath) && ! unlink($destinationPath)) {
                throw new RuntimeException('Dekripsi gagal dan file parsial tidak dapat dihapus.', 0, $exception);
            }

            throw $exception;
        }

        fclose($destination);
        if (! chmod($destinationPath, 0600)) {
            if (! unlink($destinationPath)) {
                throw new RuntimeException('File hasil dekripsi tidak dapat diamankan atau dihapus.');
            }

            throw new RuntimeException('Izin file hasil dekripsi tidak dapat dibatasi.');
        }
    }

    private function key(): string
    {
        $encodedKey = (string) config('backup.encryption_key');
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY harus berupa Base64 dari 32 byte acak.');
        }

        return $key;
    }

    private function associatedData(int $chunkIndex): string
    {
        return self::MAGIC.pack('N2', intdiv($chunkIndex, 4294967296), $chunkIndex % 4294967296);
    }

    private function readExact($stream, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($stream, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Arsip terenkripsi terpotong atau tidak dapat dibaca.');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function writeAll($stream, string $data): void
    {
        $written = 0;
        while ($written < strlen($data)) {
            $bytes = fwrite($stream, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Gagal menulis file arsip.');
            }
            $written += $bytes;
        }
    }
}
