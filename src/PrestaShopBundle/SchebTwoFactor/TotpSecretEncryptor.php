<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\SchebTwoFactor;

use PrestaShop\PrestaShop\Core\Crypto\SecretCipherInterface;

final class TotpSecretEncryptor
{
    public function __construct(private readonly SecretCipherInterface $cipher)
    {
    }

    public function encrypt(string $plain): string
    {
        return $this->cipher->encrypt($plain);
    }

    public function decrypt(string $encoded): string
    {
        return $this->cipher->decrypt($encoded);
    }
}
