<?php

/**
 * Validates generated CMS CSS and confines every selector to one CMS page.
 */
class AiNative_Copilot_Model_Cms_Css
{
    private const MAX_CSS_BYTES = 200000;

    public function process(string $css, string $scopeSelector): string
    {
        $css = trim($css);
        $scopeSelector = trim($scopeSelector);

        if ($css === '') {
            return '';
        }

        if (strlen($css) > self::MAX_CSS_BYTES) {
            throw new AiNative_Core_Exception(
                'Generated CMS CSS exceeds the 200 KB limit.'
            );
        }

        if (!preg_match('/^\.[A-Za-z_][A-Za-z0-9_-]*$/', $scopeSelector)) {
            throw new AiNative_Core_Exception(
                'Invalid CMS CSS scope selector.'
            );
        }

        $this->assertSafeCss($css);

        if (!class_exists(\Sabberworm\CSS\Parser::class)) {
            throw new AiNative_Core_Exception(
                'The sabberworm/php-css-parser dependency is not installed.'
            );
        }

        try {
            $settings = \Sabberworm\CSS\Settings::create()->beStrict();

            $document = (
                new \Sabberworm\CSS\Parser($css, $settings)
            )->parse();

            $blocks = $document->getAllDeclarationBlocks();

            if (!$blocks) {
                throw new RuntimeException(
                    'The stylesheet does not contain any CSS rules.'
                );
            }

            foreach ($blocks as $block) {
                foreach ($block->getSelectors() as $selector) {
                    $selectorText = trim($selector->getSelector());

                    if ($selectorText === '') {
                        throw new RuntimeException(
                            'An empty selector was returned.'
                        );
                    }

                    if (!$this->isScoped(
                        $selectorText,
                        $scopeSelector
                    )) {
                        $selectorText = $this->removeDocumentRoot(
                            $selectorText
                        );

                        $selectorText = $scopeSelector
                            . ($selectorText === ''
                                ? ''
                                : ' ' . $selectorText);

                        $selector->setSelector($selectorText);
                    }
                }
            }

            $processed = trim($document->render(
                \Sabberworm\CSS\OutputFormat::createCompact()
            ));
        } catch (Throwable $e) {
            throw new AiNative_Core_Exception(
                'Generated CMS CSS is invalid: ' . $e->getMessage()
            );
        }

        $this->assertSafeCss($processed);

        return $processed;
    }

    private function isScoped(
        string $selector,
        string $scopeSelector
    ): bool {
        return preg_match(
            '/^'
            . preg_quote($scopeSelector, '/')
            . '(?:$|[\s>+~.#:\[])/',
            $selector
        ) === 1;
    }

    private function removeDocumentRoot(string $selector): string
    {
        return trim((string) preg_replace(
            '/^(?:(?:html|body|:root)'
            . '(?:\s+|(?=$)|(?=[>+~.#:\[])))+/i',
            '',
            $selector
        ));
    }

    private function assertSafeCss(string $css): void
    {
        $patterns = [
            '/@(?:charset|import|namespace|font-face|'
            . '(?:-[a-z]+-)?keyframes|page|counter-style|'
            . 'property|layer|scope|starting-style)\b/i',
            '/\burl\s*\(/i',
            '/\bexpression\s*\(/i',
            '/(?:^|[;{])\s*(?:behavior|-moz-binding)\s*:/i',
            '/(?:^|[;{])\s*position\s*:\s*fixed\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $css)) {
                throw new AiNative_Core_Exception(
                    'Generated CMS CSS contains a forbidden construct.'
                );
            }
        }
    }
}