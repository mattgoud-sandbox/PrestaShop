<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\Profile\Employee\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler\SetEmployeeTwoFactorSecretHandler;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\SetEmployeeTwoFactorSecretCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;

final class SetEmployeeTwoFactorSecretHandlerTest extends TestCase
{
    public function testBothSecretValuesReplaceThePreviousValuesBeforePersistence(): void
    {
        $employee = new Employee();
        $employee->setTwoFactorSecret('old-encrypted-secret');
        $employee->setTwoFactorTotpSecretPlain('old-plain-secret');
        $command = new SetEmployeeTwoFactorSecretCommand(2, 'new-encrypted-secret', 'new-plain-secret');
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 2])->willReturn($employee);
        $persistedEmployee = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::identicalTo($employee))
            ->willReturnCallback(static function (Employee $persisted) use (&$persistedEmployee): void {
                self::assertSame('new-encrypted-secret', $persisted->getTwoFactorSecret());
                self::assertSame('new-plain-secret', $persisted->getTwoFactorTotpSecretPlain());
                $persistedEmployee = $persisted;
            });
        $entityManager->expects(self::once())->method('flush')
            ->willReturnCallback(static function () use ($employee, &$persistedEmployee): void {
                self::assertInstanceOf(Employee::class, $persistedEmployee);
                self::assertSame($employee, $persistedEmployee);
                self::assertSame('new-encrypted-secret', $persistedEmployee->getTwoFactorSecret());
                self::assertSame('new-plain-secret', $persistedEmployee->getTwoFactorTotpSecretPlain());
            });
        $handler = new SetEmployeeTwoFactorSecretHandler($repository, $entityManager);

        $handler->handle($command);

        self::assertSame('new-encrypted-secret', $employee->getTwoFactorSecret());
        self::assertSame('new-plain-secret', $employee->getTwoFactorTotpSecretPlain());
    }

    public function testMissingEmployeeRetainsTheCommandEmployeeIdAndDoesNotPersist(): void
    {
        $command = new SetEmployeeTwoFactorSecretCommand(2, 'encrypted-test-secret', 'plain-test-secret');
        $repository = $this->createMock(EmployeeRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 2])->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $handler = new SetEmployeeTwoFactorSecretHandler($repository, $entityManager);

        try {
            $handler->handle($command);
        } catch (EmployeeNotFoundException $exception) {
            self::assertSame($command->getEmployeeId(), $exception->getEmployeeId());
            self::assertSame('Employee with id "2" cannot be found.', $exception->getMessage());

            return;
        }

        self::fail('Expected EmployeeNotFoundException.');
    }
}
