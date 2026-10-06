<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\PrestaShopBundle\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Yaml\Yaml;

final class TwoFactorConfigurationTest extends TestCase
{
    public function testLegacyAndAuthenticatorManagerTokensRequireTwoFactorAuthentication(): void
    {
        $configuration = Yaml::parseFile(_PS_ROOT_DIR_ . '/app/config/scheb_two_factor.yml');
        $securityTokens = $configuration['scheb_two_factor']['security_tokens'];

        self::assertContains(UsernamePasswordToken::class, $securityTokens);
        self::assertContains(PostAuthenticationToken::class, $securityTokens);
    }
}
