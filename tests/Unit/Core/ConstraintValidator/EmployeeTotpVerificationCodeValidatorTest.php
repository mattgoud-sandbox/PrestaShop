<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Core\ConstraintValidator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\EmployeeTotpVerificationCode;
use PrestaShop\PrestaShop\Core\ConstraintValidator\EmployeeTotpVerificationCodeValidator;
use PrestaShop\PrestaShop\Core\Employee\ContextEmployeeProviderInterface;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<EmployeeTotpVerificationCodeValidator>
 */
final class EmployeeTotpVerificationCodeValidatorTest extends ConstraintValidatorTestCase
{
    private ContextEmployeeProviderInterface&MockObject $employeeProvider;
    private EmployeeRepository&MockObject $employeeRepository;
    private TotpAuthenticatorInterface&MockObject $totpAuthenticator;

    protected function createValidator(): EmployeeTotpVerificationCodeValidator
    {
        $this->employeeProvider = $this->createMock(ContextEmployeeProviderInterface::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->totpAuthenticator = $this->createMock(TotpAuthenticatorInterface::class);

        return new EmployeeTotpVerificationCodeValidator(
            $this->totpAuthenticator,
            $this->employeeProvider,
            $this->employeeRepository
        );
    }

    public function testWrongConstraintThrowsUnexpectedTypeException(): void
    {
        $this->expectNoCredentialLookup();
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('123456', new NotBlank());
    }

    public function testNonFormRootSkipsValidation(): void
    {
        $this->setRoot('not-a-form');
        $this->expectNoCredentialLookup();

        $this->validator->validate('123456', new EmployeeTotpVerificationCode());

        $this->assertNoViolation();
    }

    public function testRootWithoutTotpEnabledChildSkipsValidation(): void
    {
        $root = $this->createMock(FormInterface::class);
        $root->expects(self::once())->method('has')->with('two_factor_totp_enabled')->willReturn(false);
        $root->expects(self::never())->method('get');
        $this->setRoot($root);
        $this->expectNoCredentialLookup();

        $this->validator->validate('123456', new EmployeeTotpVerificationCode());

        $this->assertNoViolation();
    }

    public function testDisabledTotpChildSkipsValidation(): void
    {
        $this->setTotpActivationRequested(false);
        $this->expectNoCredentialLookup();

        $this->validator->validate('', new EmployeeTotpVerificationCode());

        $this->assertNoViolation();
    }

    #[DataProvider('emptyVerificationCodes')]
    public function testEnabledTotpRequiresANonemptyVerificationCode(?string $value): void
    {
        $this->setTotpActivationRequested(true);
        $this->expectNoCredentialLookup();

        $this->validator->validate($value, new EmployeeTotpVerificationCode());

        $this->buildViolation('This field cannot be empty.')->assertRaised();
    }

    public static function emptyVerificationCodes(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    public function testNonemptyCodeIsSkippedWhenAuthenticatorIsUnavailable(): void
    {
        $this->validator = new EmployeeTotpVerificationCodeValidator(null, $this->employeeProvider, $this->employeeRepository);
        $this->setTotpActivationRequested(true);
        $this->expectNoCredentialLookup();

        $this->validator->validate('123456', new EmployeeTotpVerificationCode());

        $this->assertNoViolation();
    }

    #[DataProvider('verificationResults')]
    public function testVerificationUsesTheCurrentEmployeeAndReportsInvalidCodes(bool $isValid): void
    {
        $this->setTotpActivationRequested(true);
        $employee = new Employee();
        $this->employeeProvider->expects(self::once())->method('getId')->willReturn(21);
        $this->employeeRepository->expects(self::once())->method('findOneBy')->with(['id' => 21])->willReturn($employee);
        $this->totpAuthenticator->expects(self::once())->method('checkCode')
            ->with(self::identicalTo($employee), '654321')->willReturn($isValid);
        $constraint = new EmployeeTotpVerificationCode();
        $constraint->message = 'Invalid verification code: {{ string }}.';

        $this->validator->validate('654321', $constraint);

        if ($isValid) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation($constraint->message)
                ->setParameter('{{ string }}', '654321')
                ->assertRaised();
        }
    }

    public static function verificationResults(): array
    {
        return [
            'valid code' => [true],
            'invalid code' => [false],
        ];
    }

    private function setTotpActivationRequested(bool $enabled): void
    {
        $child = $this->createMock(FormInterface::class);
        $child->expects(self::once())->method('getData')->willReturn($enabled);
        $root = $this->createMock(FormInterface::class);
        $root->expects(self::once())->method('has')->with('two_factor_totp_enabled')->willReturn(true);
        $root->expects(self::once())->method('get')->with('two_factor_totp_enabled')->willReturn($child);
        $this->setRoot($root);
    }

    private function expectNoCredentialLookup(): void
    {
        $this->employeeProvider->expects(self::never())->method('getId');
        $this->employeeRepository->expects(self::never())->method('findOneBy');
        $this->totpAuthenticator->expects(self::never())->method('checkCode');
    }
}
