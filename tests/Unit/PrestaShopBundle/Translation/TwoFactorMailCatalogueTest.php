<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\PrestaShopBundle\Translation;

use PHPUnit\Framework\TestCase;
use PrestaShop\TranslationToolsBundle\Translation\Extractor\PhpExtractor;
use PrestaShop\TranslationToolsBundle\Translation\Extractor\TwigExtractor;
use PrestaShop\TranslationToolsBundle\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwoFactorMailCatalogueTest extends TestCase
{
    public function testTwoFactorMailSubjectIsInTheDefaultCatalogue(): void
    {
        $extracted = new MessageCatalogue('en');
        (new PhpExtractor())->extract(
            _PS_ROOT_DIR_ . '/src/Adapter/TotMailer/TwoFactorAuthCodePrestashopMailer.php',
            $extracted
        );
        self::assertArrayHasKey('Your authentication code', $extracted->all('Emails.Subject'));

        $catalogue = (new XliffFileLoader())->load(
            _PS_ROOT_DIR_ . '/translations/default/EmailsSubject.xlf',
            'en',
            'Emails.Subject'
        );
        self::assertTrue($catalogue->defines('Your authentication code', 'Emails.Subject'));
    }

    public function testAllTwoFactorMailMessagesAreInTheDefaultCatalogue(): void
    {
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension(new TranslationExtension());
        $extracted = new MessageCatalogue('en');
        (new TwigExtractor($twig))->extract(
            _PS_ROOT_DIR_ . '/mails/themes/modern/core/two_factor_auth_code.html.twig',
            $extracted
        );

        $messages = $extracted->all('Emails.Body');
        self::assertArrayHasKey('Authentication code', $messages);
        self::assertArrayHasKey('Your authentication code is', $messages);

        $catalogue = (new XliffFileLoader())->load(
            _PS_ROOT_DIR_ . '/translations/default/EmailsBody.xlf',
            'en',
            'Emails.Body'
        );
        foreach (array_keys($messages) as $message) {
            self::assertTrue($catalogue->defines($message, 'Emails.Body'), 'Missing mail translation: ' . $message);
        }
    }
}
