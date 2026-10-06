<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Adapter\MailTemplate;

use Context;
use Language;
use Link;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\MailTemplate\MailPartialTemplateRenderer;
use PrestaShop\PrestaShop\Adapter\MailTemplate\MailPreviewVariablesBuilder;
use PrestaShop\PrestaShop\Adapter\Shipment\OrderShipmentService;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;
use PrestaShop\PrestaShop\Core\Employee\ContextEmployeeProviderInterface;
use PrestaShop\PrestaShop\Core\Localization\Locale;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use ReflectionProperty;
use Shop;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

final class MailPreviewVariablesBuilderTest extends TestCase
{
    public function testTwoFactorMailPreviewUsesASampleAuthenticationCode(): void
    {
        $context = $this->createMock(Context::class);
        $context->link = $this->createMock(Link::class);
        $context->link->method('getBaseLink')->willReturn('https://example.com/');
        $context->link->method('getPageLink')->willReturn('https://example.com/');
        $context->shop = $this->createMock(Shop::class);
        $context->shop->name = 'Preview shop';
        $context->language = $this->createMock(Language::class);
        $context->language->id = 1;

        $legacyContext = $this->createMock(LegacyContext::class);
        $legacyContext->method('getContext')->willReturn($context);
        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->method('get')->willReturn('');
        $employeeProvider = $this->createMock(ContextEmployeeProviderInterface::class);
        $employeeProvider->method('getData')->willReturn([
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane@example.com',
        ]);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $builder = new MailPreviewVariablesBuilder(
            $configuration,
            $legacyContext,
            $employeeProvider,
            $this->createMock(MailPartialTemplateRenderer::class),
            $this->createMock(Locale::class),
            $translator,
            $this->createMock(OrderShipmentService::class)
        );

        $previousContext = Context::getContext();
        $shops = new ReflectionProperty(Shop::class, 'shops');
        $previousShops = $shops->getValue();
        Context::setInstanceForTesting($context);
        $shops->setValue(null, []);
        try {
            $layout = new Layout('two_factor_auth_code', '@MailThemes/modern/core/two_factor_auth_code.html.twig', '');
            $variables = $builder->buildTemplateVariables($layout);
        } finally {
            Context::setInstanceForTesting($previousContext);
            $shops->setValue(null, $previousShops);
        }

        self::assertArrayHasKey('{auth_code}', $variables);
        self::assertSame('123456', $variables['{auth_code}']);
        self::assertMatchesRegularExpression('/^\d{6}$/', $variables['{auth_code}']);

        $loader = new FilesystemLoader();
        $loader->addPath(_PS_ROOT_DIR_ . '/mails/themes', 'MailThemes');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $rendered = $twig->render($layout->getHtmlPath(), ['locale' => 'en']);
        self::assertStringContainsString('{auth_code}', $rendered);
        $preview = strtr($rendered, $variables);
        self::assertStringContainsString('<strong>123456</strong>', $preview);
        self::assertStringNotContainsString('{auth_code}', $preview);
    }
}
