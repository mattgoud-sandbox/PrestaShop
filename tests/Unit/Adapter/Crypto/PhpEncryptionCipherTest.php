<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\Crypto;

use PhpEncryption;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Crypto\PhpEncryptionCipher;
use RuntimeException;

final class PhpEncryptionCipherTest extends TestCase
{
    public function testSecretRoundTrip(): void
    {
        $cipher = new PhpEncryptionCipher(PhpEncryption::createNewRandomKey());
        $plain = 'test-totp-secret';

        $encoded = $cipher->encrypt($plain);

        self::assertNotSame($plain, $encoded);
        self::assertSame($plain, $cipher->decrypt($encoded));
    }

    public function testCompatibilityWithLegacyEncryption(): void
    {
        $key = PhpEncryption::createNewRandomKey();
        $cipher = new PhpEncryptionCipher($key);
        $legacy = new PhpEncryption($key);
        $plain = 'test-totp-secret';

        self::assertSame($plain, $legacy->decrypt($cipher->encrypt($plain)));
        self::assertSame($plain, $cipher->decrypt($legacy->encrypt($plain)));
    }

    public function testEachOperationRestoresItsKeyAfterAnotherCipherIsUsed(): void
    {
        $firstKey = PhpEncryption::createNewRandomKey();
        $secondKey = PhpEncryption::createNewRandomKey();
        $first = new PhpEncryptionCipher($firstKey);
        $second = new PhpEncryptionCipher($secondKey);

        $firstEncoded = $first->encrypt('first-secret');
        $secondEncoded = $second->encrypt('second-secret');

        self::assertSame('first-secret', $first->decrypt($firstEncoded));
        self::assertSame('second-secret', $second->decrypt($secondEncoded));
        self::assertSame('first-secret', (new PhpEncryption($firstKey))->decrypt($firstEncoded));
        self::assertSame('second-secret', (new PhpEncryption($secondKey))->decrypt($secondEncoded));
    }

    public function testFailedDecryptionThrowsRuntimeException(): void
    {
        $cipher = new PhpEncryptionCipher(PhpEncryption::createNewRandomKey());
        $otherCipher = new PhpEncryptionCipher(PhpEncryption::createNewRandomKey());
        $encoded = $otherCipher->encrypt('test-totp-secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to decrypt the secret.');

        $cipher->decrypt($encoded);
    }
}
