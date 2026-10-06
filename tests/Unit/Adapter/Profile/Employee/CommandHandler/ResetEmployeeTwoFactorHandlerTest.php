<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\Profile\Employee\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler\ResetEmployeeTwoFactorHandler;
use PrestaShop\PrestaShop\Core\Context\Employee as ContextEmployee;
use PrestaShop\PrestaShop\Core\Context\EmployeeContext;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\ResetEmployeeTwoFactorCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeCannotChangeItselfException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;
use ReflectionProperty;

final class ResetEmployeeTwoFactorHandlerTest extends TestCase
{
    public function testResetClearsAllCredentialsInOneFlushAndRetainsRequiredPolicy(): void
    {
        $employee = $this->createEmployee();
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 2])->willReturn($employee);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::identicalTo($employee));
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(function () use ($employee): void {
            $this->assertResetState($employee);
        });

        $handler = new ResetEmployeeTwoFactorHandler($repository, $entityManager, $this->createEmployeeContext(1));
        $handler->handle(new ResetEmployeeTwoFactorCommand(2));

        $this->assertResetState($employee);
    }

    public function testMissingEmployeeRetainsTheCommandEmployeeIdAndDoesNotPersist(): void
    {
        $command = new ResetEmployeeTwoFactorCommand(2);
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 2])->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $handler = new ResetEmployeeTwoFactorHandler($repository, $entityManager, $this->createEmployeeContext(1));

        try {
            $handler->handle($command);
        } catch (EmployeeNotFoundException $exception) {
            self::assertSame($command->getEmployeeId(), $exception->getEmployeeId());
            self::assertSame('Employee with id "2" cannot be found.', $exception->getMessage());

            return;
        }

        self::fail('Expected EmployeeNotFoundException.');
    }

    public function testSelfResetIsRejectedWithoutMutationOrPersistence(): void
    {
        $employee = $this->createEmployee();
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 2])->willReturn($employee);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $handler = new ResetEmployeeTwoFactorHandler($repository, $entityManager, $this->createEmployeeContext(2));

        try {
            $handler->handle(new ResetEmployeeTwoFactorCommand(2));
        } catch (EmployeeCannotChangeItselfException $exception) {
            self::assertSame(EmployeeCannotChangeItselfException::CANNOT_CHANGE_STATUS, $exception->getCode());
            self::assertTrue($employee->getTwoFactorEnabled());
            self::assertTrue($employee->isTotpAuthenticationEnabled());
            self::assertTrue($employee->getTwoFactorEmailEnabled());
            self::assertTrue($employee->isTwoFactorRequired());
            self::assertSame('encrypted-test-secret', $employee->getTwoFactorSecret());
            self::assertSame('plain-test-secret', $employee->getTwoFactorTotpSecretPlain());
            self::assertSame('123456', $employee->getEmailAuthCode());
            self::assertSame([hash('sha256', 'old-backup-code')], $employee->getTwoFactorBackupCodes());

            return;
        }

        self::fail('Expected EmployeeCannotChangeItselfException.');
    }

    private function createEmployee(): Employee
    {
        $employee = new Employee();
        (new ReflectionProperty(Employee::class, 'id'))->setValue($employee, 2);
        (new ReflectionProperty(Employee::class, 'twoFactorTotEnabled'))->setValue($employee, true);
        $employee->setTwoFactorEnabled(true);
        $employee->setTwoFactorRequired(true);
        $employee->setTwoFactorEmailEnabled(true);
        $employee->setTwoFactorSecret('encrypted-test-secret');
        $employee->setTwoFactorTotpSecretPlain('plain-test-secret');
        $employee->setEmailAuthCode('123456');
        $employee->setTwoFactorBackupCodes([hash('sha256', 'old-backup-code')]);

        return $employee;
    }

    private function createEmployeeContext(int $id): EmployeeContext
    {
        $employee = $this->createMock(ContextEmployee::class);
        $employee->method('getId')->willReturn($id);

        return new EmployeeContext($employee, [1]);
    }

    private function assertResetState(Employee $employee): void
    {
        self::assertFalse($employee->getTwoFactorEnabled());
        self::assertFalse((new ReflectionProperty(Employee::class, 'twoFactorTotEnabled'))->getValue($employee));
        self::assertFalse($employee->getTwoFactorEmailEnabled());
        self::assertTrue($employee->isTwoFactorRequired());
        self::assertNull($employee->getTwoFactorSecret());
        self::assertNull($employee->getTwoFactorTotpSecretPlain());
        self::assertNull($employee->getEmailAuthCode());
        self::assertNull($employee->getTwoFactorBackupCodes());
    }
}
