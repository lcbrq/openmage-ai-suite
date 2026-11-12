<?php

/**
 * Materializes database-backed CMS page CSS as a hashed media asset.
 */
class AiNative_Copilot_Model_Cms_Style
{
    public const DATA_KEY = 'ainative_css';
    public const MEDIA_DIRECTORY = 'ainative/cms';
    private const MAX_CSS_BYTES = 200000;

    public function ensureAsset(Mage_Cms_Model_Page $page): ?string
    {
        $pageId = (int) $page->getId();
        $css = trim((string) $page->getData(self::DATA_KEY));

        if ($pageId <= 0 || $css === '') {
            return null;
        }

        if (strlen($css) > self::MAX_CSS_BYTES) {
            $this->logFailure($pageId, 'CSS exceeds the 200 KB limit.');
            return null;
        }

        $filename = sprintf(
            'page-%d-%s.css',
            $pageId,
            substr(hash('sha256', $css), 0, 16),
        );

        $relativePath = self::MEDIA_DIRECTORY . '/' . $filename;
        $absolutePath = Mage::getBaseDir('media')
            . DS
            . str_replace('/', DS, $relativePath);

        if (is_file($absolutePath)) {
            return $relativePath;
        }

        try {
            Mage::getConfig()->getOptions()->createDirIfNotExists(dirname($absolutePath));
            $written = file_put_contents($absolutePath, $css, LOCK_EX);

            if ($written === false || $written !== strlen($css)) {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }

                throw new RuntimeException(
                    'The stylesheet could not be written completely.'
                );
            }
        } catch (Throwable $e) {
            $this->logFailure($pageId, $e->getMessage());
            return null;
        }

        return $relativePath;
    }

    public function getAssetUrl(string $relativePath): string
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
            . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public function preparePage(
        Mage_Cms_Model_Page $page,
        string $assetUrl
    ): void {
        $pageId = (int) $page->getId();

        if ($pageId <= 0) {
            return;
        }

        $scopeClass = $this->getScopeClass($pageId);
        $content = (string) $page->getContent();

        if (strpos($content, $scopeClass) === false) {
            $page->setContent(
                '<div class="ainative-cms-page ' . $scopeClass . '">'
                . $content
                . '</div>',
            );
        }

        $linkUpdate = '<reference name="head">'
            . '<action method="addLinkRel">'
            . '<rel>stylesheet</rel>'
            . '<href>'
            . htmlspecialchars($assetUrl, ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</href>'
            . '</action>'
            . '</reference>';

        $page->setLayoutUpdateXml(
            (string) $page->getLayoutUpdateXml() . $linkUpdate,
        );

        if (trim((string) $page->getCustomLayoutUpdateXml()) !== '') {
            $page->setCustomLayoutUpdateXml(
                (string) $page->getCustomLayoutUpdateXml() . $linkUpdate,
            );
        }
    }

    public function getScopeClass(int $pageId): string
    {
        return 'ainative-cms-page-' . $pageId;
    }

    public function getScopeSelector(int $pageId): string
    {
        return '.' . $this->getScopeClass($pageId);
    }

    private function logFailure(int $pageId, string $message): void
    {
        Mage::helper('ainative_core')->log(
            'CMS stylesheet materialization failed: ' . $message,
            ['page_id' => $pageId],
            Zend_Log::ERR,
        );
    }
}