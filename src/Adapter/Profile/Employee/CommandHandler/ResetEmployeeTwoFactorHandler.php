<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsCommandHandler;
use PrestaShop\PrestaShop\Core\Context\EmployeeContext;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\ResetEmployeeTwoFactorCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeCannotChangeItselfException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;

#[AsCommandHandler]
final class ResetEmployeeTwoFactorHandler
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployeeContext $employeeContext
    ) {
    }

    public function handle(ResetEmployeeTwoFactorCommand $command): void
    {
        $employeeId = $command->getEmployeeId();
        /** @var Employee|null $employee */
        $employee = $this->employeeRepository->findOneBy(['id' => $employeeId->getValue()]);
        if ($employee === null) {
            throw new EmployeeNotFoundException($employeeId, sprintf('Employee with id "%s" cannot be found.', $employeeId->getValue()));
        }

        if ($this->employeeContext->getEmployee()?->getId() === $employeeId->getValue()) {
            throw new EmployeeCannotChangeItselfException('Employee cannot change status of itself.', EmployeeCannotChangeItselfException::CANNOT_CHANGE_STATUS);
        }

        $employee->resetTwoFactorAuthentication();
        $this->entityManager->persist($employee);
        $this->entityManager->flush();
    }
}
