<?php

class AiNative_Copilot_Model_Observer
{
    /**
     * Add "Generate content with AI" to the product grid mass actions.
     */
    public function addProductGridMassaction(Varien_Event_Observer $observer): void
    {
        $block = $observer->getEvent()->getBlock();
        if (!$block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid || !Mage::helper('ainative_copilot')->isEnabled()) {
            return;
        }
        if (!Mage::getSingleton('admin/session')->isAllowed('ainative/copilot')) {
            return;
        }
        $h = Mage::helper('ainative_core');
        $fieldOptions = [];
        foreach (AiNative_Copilot_Model_Generator::PRODUCT_FIELDS as $f) {
            $fieldOptions[] = ['value' => $f, 'label' => $f];
        }
        $block->getMassactionBlock()->addItem('ainative_generate', [
            'label' => $h->__('AI: Generate content drafts'),
            'url' => $block->getUrl('adminhtml/ainative_job/massGenerate', ['_current' => true]),
            'additional' => [
                'fields' => [
                    'name' => 'fields',
                    'type' => 'multiselect',
                    'class' => 'required-entry',
                    'label' => $h->__('Fields'),
                    'values' => $fieldOptions,
                ],
                'tone' => [
                    'name' => 'tone',
                    'type' => 'select',
                    'label' => $h->__('Tone'),
                    'values' => Mage::getModel('ainative_copilot/config_source_tone')->toOptionArray(),
                ],
            ],
        ]);
    }

    /**
     * Flag the product form so the layout can inject the copilot button script.
     */
    public function markProductForm(Varien_Event_Observer $observer): void
    {
        Mage::register('ainative_copilot_product_form', true, true);
    }

    public function materializeCmsPageStyle(
        Varien_Event_Observer $observer
    ): void {
        $page = $observer->getEvent()->getPage()
            ?: $observer->getEvent()->getObject();

        if (!$page instanceof Mage_Cms_Model_Page) {
            return;
        }

        Mage::getModel('ainative_copilot/cms_style')->ensureAsset($page);
    }

    public function prepareCmsPageStyle(
        Varien_Event_Observer $observer
    ): void {

        $page = $observer->getEvent()->getPage();

        if (!$page instanceof Mage_Cms_Model_Page) {
            return;
        }

        /** @var AiNative_Copilot_Model_Cms_Style $style */
        $style = Mage::getModel('ainative_copilot/cms_style');
        $relativePath = $style->ensureAsset($page);

        if ($relativePath === null) {
            return;
        }

        $style->preparePage(
            $page,
            $style->getAssetUrl($relativePath)
        );
    }
}
