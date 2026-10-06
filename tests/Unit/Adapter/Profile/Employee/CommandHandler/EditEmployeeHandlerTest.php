<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\Profile\Employee\CommandHandler;

use Employee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler\EditEmployeeHandler;
use PrestaShop\PrestaShop\Core\Crypto\Hashing;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\EditEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeConstraintException;
use PrestaShop\PrestaShop\Core\Employee\Access\ProfileAccessCheckerInterface;
use PrestaShop\PrestaShop\Core\Employee\ContextEmployeeProviderInterface;
use ReflectionMethod;

final class EditEmployeeHandlerTest extends TestCase
{
    public function testEnabledTwoFactorAuthenticationWithoutAProviderIsRejectedBeforeUpdate(): void
    {
        $employee = $this->createEmployee();
        $employee->expects(self::never())->method('update');
        $command = $this->createCommand(true, false, false);

        $this->expectException(EmployeeConstraintException::class);
        $this->expectExceptionCode(EmployeeConstraintException::INVALID_TWO_FACTOR_CONFIGURATION);
        $this->expectExceptionMessage('Enabled two-factor authentication requires email or TOTP authentication.');

        $this->invokeUpdate($employee, $command);
    }

    #[DataProvider('validTwoFactorConfigurations')]
    public function testValidTwoFactorConfigurationIsUpdated(bool $enabled, bool $emailEnabled, bool $totpEnabled): void
    {
        $employee = $this->createEmployee();
        $employee->expects(self::once())->method('update')->willReturn(true);

        $this->invokeUpdate($employee, $this->createCommand($enabled, $emailEnabled, $totpEnabled));

        self::assertSame($enabled, $employee->two_factor_enabled);
        self::assertSame($emailEnabled, $employee->two_factor_email_enabled);
        self::assertSame($totpEnabled, $employee->two_factor_totp_enabled);
    }

    public static function validTwoFactorConfigurations(): array
    {
        return [
            'email only' => [true, true, false],
            'TOTP only' => [true, false, true],
            'both providers' => [true, true, true],
            'disabled without providers' => [false, false, false],
        ];
    }

    private function createEmployee(): Employee&MockObject
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLastElementsForNotify', 'isSuperAdmin', 'update'])
            ->getMock();
        $employee->id = 1;
        $employee->method('getLastElementsForNotify')->willReturn(0);
        $employee->method('isSuperAdmin')->willReturn(false);

        return $employee;
    }

    private function createCommand(bool $enabled, bool $emailEnabled, bool $totpEnabled): EditEmployeeCommand
    {
        return (new EditEmployeeCommand(1))
            ->setFirstName('Jane')
            ->setLastName('Doe')
            ->setEmail('jane@example.com')
            ->setDefaultPageId(1)
            ->setLanguageId(1)
            ->setProfileId(1)
            ->setActive(true)
            ->setShopAssociation([1])
            ->setTwoFactorEnabled($enabled)
            ->setTwoFactorEmailEnabled($emailEnabled)
            ->setTwoFactorTotEnabled($totpEnabled);
    }

    private function invokeUpdate(Employee $employee, EditEmployeeCommand $command): void
    {
        $employeeProvider = $this->createMock(ContextEmployeeProviderInterface::class);
        $employeeProvider->method('getId')->willReturn(1);
        $handler = new EditEmployeeHandler(
            $this->createMock(Hashing::class),
            $this->createMock(ProfileAccessCheckerInterface::class),
            $employeeProvider,
            $this->createMock(LegacyContext::class)
        );
        $update = new ReflectionMethod(EditEmployeeHandler::class, 'updateEmployeeWithCommandData');
        $update->invoke($handler, $employee, $command);
    }
}
