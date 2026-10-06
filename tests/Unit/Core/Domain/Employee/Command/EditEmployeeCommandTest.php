<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Core\Domain\Employee\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\EditEmployeeCommand;

final class EditEmployeeCommandTest extends TestCase
{
    public function testOmittedTwoFactorFlagsAreNull(): void
    {
        $command = new EditEmployeeCommand(1);

        self::assertNull($command->getTwoFactorEnabled());
        self::assertNull($command->getTwoFactorTotEnabled());
        self::assertNull($command->getTwoFactorEmailEnabled());
    }

    #[DataProvider('explicitFlagValues')]
    public function testExplicitBooleanFlagsArePreserved(bool $value): void
    {
        $command = (new EditEmployeeCommand(1))
            ->setTwoFactorEnabled($value)
            ->setTwoFactorTotEnabled($value)
            ->setTwoFactorEmailEnabled($value);

        self::assertSame($value, $command->getTwoFactorEnabled());
        self::assertSame($value, $command->getTwoFactorTotEnabled());
        self::assertSame($value, $command->getTwoFactorEmailEnabled());
    }

    public static function explicitFlagValues(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
    }
}
