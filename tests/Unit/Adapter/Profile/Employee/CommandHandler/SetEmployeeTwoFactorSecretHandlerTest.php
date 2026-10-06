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
use PrestaShopBundle\Entity\Repository\EmployeeRepository;

final class SetEmployeeTwoFactorSecretHandlerTest extends TestCase
{
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
