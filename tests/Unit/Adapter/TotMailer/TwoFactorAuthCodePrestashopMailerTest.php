<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\TotMailer;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\TotMailer\TwoFactorAuthCodePrestashopMailer;
use PrestaShop\PrestaShop\Core\Context\ShopContext;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Translation\TranslatorInterface;
use Symfony\Component\Mailer\Exception\TransportException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TwoFactorAuthCodePrestashopMailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertFalse(class_exists('Mail', false), 'Mail must not be loaded before the isolated stub is installed.');
        class_alias(MailSendStub::class, 'Mail');
        MailSendStub::$result = true;
        MailSendStub::$calls = [];
        if (!defined('_PS_MAIL_DIR_')) {
            define('_PS_MAIL_DIR_', _PS_ROOT_DIR_ . '/mails/');
        }
    }

    public function testFailedSendThrowsTransportException(): void
    {
        MailSendStub::$result = false;
        $mailer = $this->createMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the two-factor authentication code.');

        $mailer->sendAuthCode($this->createEmployee());
    }

    public function testSuccessfulSendUsesTheEmployeeAndShopData(): void
    {
        $this->createMailer()->sendAuthCode($this->createEmployee());

        self::assertCount(1, MailSendStub::$calls);
        $arguments = MailSendStub::$calls[0];
        self::assertSame(2, $arguments[0]);
        self::assertSame('two_factor_auth_code', $arguments[1]);
        self::assertSame('Authentication code subject', $arguments[2]);
        self::assertSame([
            '{auth_code}' => '123456',
            '{firstname}' => 'Jane',
            '{lastname}' => 'Doe',
        ], $arguments[3]);
        self::assertSame('jane@example.com', $arguments[4]);
        self::assertSame(_PS_MAIL_DIR_, $arguments[10]);
        self::assertSame(3, $arguments[12]);
    }

    private function createMailer(): TwoFactorAuthCodePrestashopMailer
    {
        $shopContext = $this->createMock(ShopContext::class);
        $shopContext->method('getId')->willReturn(3);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('en-US');
        $translator->expects(self::once())->method('trans')
            ->with('Your authentication code', [], 'Emails.Subject', 'fr-FR')
            ->willReturn('Authentication code subject');

        return new TwoFactorAuthCodePrestashopMailer($shopContext, $translator);
    }

    private function createEmployee(): Employee
    {
        $language = $this->createMock(Lang::class);
        $language->method('getId')->willReturn(2);
        $language->method('getLocale')->willReturn('fr-FR');
        $employee = new Employee();
        $employee->setDefaultLanguageId($language);
        $employee->setFirstName('Jane');
        $employee->setLastName('Doe');
        $employee->setEmail('jane@example.com');
        $employee->setEmailAuthCode('123456');

        return $employee;
    }
}

final class MailSendStub
{
    public static bool $result = true;

    /** @var array<array<mixed>> */
    public static array $calls = [];

    public static function Send(mixed ...$arguments): bool
    {
        self::$calls[] = $arguments;

        return self::$result;
    }
}
