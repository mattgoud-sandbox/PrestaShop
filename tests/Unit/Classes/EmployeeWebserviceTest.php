<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Classes;

use Employee;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EmployeeWebserviceTest extends TestCase
{
    public function testTwoFactorFlagsAreHiddenAndExcludedFromWebserviceFields(): void
    {
        $parameters = $this->generateWebserviceParameters();

        foreach ([
            'two_factor_email_enabled',
            'two_factor_enabled',
            'two_factor_required',
            'two_factor_totp_enabled',
        ] as $field) {
            self::assertArrayHasKey($field, Employee::$definition['fields']);
            self::assertContains($field, $parameters['hidden_fields']);
            self::assertArrayNotHasKey($field, $parameters['fields']);
        }
    }

    public function testTwoFactorSecretIsStillPersistedButExcludedFromTheLegacyResource(): void
    {
        $parameters = $this->generateWebserviceParameters();

        self::assertArrayHasKey('two_factor_totp_secret', Employee::$definition['fields']);
        self::assertContains('two_factor_totp_secret', $parameters['hidden_fields']);
        self::assertArrayNotHasKey('two_factor_totp_secret', $parameters['fields']);
        self::assertArrayHasKey('firstname', $parameters['fields']);
        self::assertArrayHasKey('email', $parameters['fields']);
    }

    private function generateWebserviceParameters(): array
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cacheFieldsRequiredDatabase', 'getCachedFieldsRequiredDatabase'])
            ->getMock();
        $employee->expects(self::once())->method('cacheFieldsRequiredDatabase');
        $employee->expects(self::once())->method('getCachedFieldsRequiredDatabase')->willReturn([]);

        $definition = new ReflectionProperty(Employee::class, 'def');
        $definition->setValue($employee, Employee::$definition);

        return $employee->getWebserviceParameters();
    }
}
