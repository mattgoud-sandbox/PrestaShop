<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Crypto;

use PhpEncryption;
use PrestaShop\PrestaShop\Core\Crypto\SecretCipherInterface;
use RuntimeException;

final class PhpEncryptionCipher implements SecretCipherInterface
{
    public function __construct(private readonly string $key)
    {
    }

    public function encrypt(string $plain): string
    {
        return (new PhpEncryption($this->key))->encrypt($plain);
    }

    public function decrypt(string $encoded): string
    {
        $plain = (new PhpEncryption($this->key))->decrypt($encoded);
        if ($plain === false) {
            throw new RuntimeException('Unable to decrypt the secret.');
        }

        return $plain;
    }
}
