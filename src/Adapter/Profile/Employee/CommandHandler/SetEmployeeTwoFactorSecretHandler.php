<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsCommandHandler;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\SetEmployeeTwoFactorSecretCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\CommandHandler\SetEmployeeTwoFactorSecretHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\Entity\Employee\Employee as EntityEmployee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;

/**
 * Handles the command that stores the two-factor authentication secret
 *
 * @internal
 */
#[AsCommandHandler]
final class SetEmployeeTwoFactorSecretHandler implements SetEmployeeTwoFactorSecretHandlerInterface
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(SetEmployeeTwoFactorSecretCommand $command): void
    {
        /** @var EntityEmployee|null $employee */
        $employee = $this->employeeRepository->findOneBy([
            'id' => $command->getEmployeeId()->getValue(),
        ]);
        if ($employee === null) {
            throw new EmployeeNotFoundException(
                $command->getEmployeeId(),
                sprintf('Employee with id "%s" cannot be found.', $command->getEmployeeId()->getValue())
            );
        }

        $employee
            ->setTwoFactorSecret($command->getSecret())
            ->setTwoFactorTotpSecretPlain($command->getSecretPlain());

        $this->entityManager->persist($employee);
        $this->entityManager->flush();
    }
}
