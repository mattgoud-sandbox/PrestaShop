<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\PrestaShopBundle\SchebTwoFactor;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Crypto\SecretCipherInterface;
use PrestaShopBundle\SchebTwoFactor\TotpSecretEncryptor;

final class TotpSecretEncryptorTest extends TestCase
{
    public function testEncryptDelegatesToTheCipher(): void
    {
        $cipher = $this->createMock(SecretCipherInterface::class);
        $cipher->expects(self::once())->method('encrypt')->with('plain-secret')->willReturn('encoded-secret');

        self::assertSame('encoded-secret', (new TotpSecretEncryptor($cipher))->encrypt('plain-secret'));
    }

    public function testDecryptDelegatesToTheCipher(): void
    {
        $cipher = $this->createMock(SecretCipherInterface::class);
        $cipher->expects(self::once())->method('decrypt')->with('encoded-secret')->willReturn('plain-secret');

        self::assertSame('plain-secret', (new TotpSecretEncryptor($cipher))->decrypt('encoded-secret'));
    }
}
