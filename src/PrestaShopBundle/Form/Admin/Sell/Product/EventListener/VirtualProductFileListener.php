<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PrestaShopBundle\Form\Admin\Sell\Product\EventListener;

use PrestaShopBundle\Form\FormCloner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class VirtualProductFileListener implements EventSubscriberInterface
{
    /**
     * @var FormCloner
     */
    private $formCloner;

    public function __construct(
        FormCloner $formCloner
    ) {
        $this->formCloner = $formCloner;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SUBMIT => 'adaptFormConstraints',
        ];
    }

    /**
     * Adjusts form constraints depending on what action is being done regarding virtual product file,
     * so that we don't invalidate form when there is no intention to upload/update a file (a.k.a. the field has_file is falsy)
     *
     * @param FormEvent $event
     */
    public function adaptFormConstraints(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();

        // Remove file & name constraints if there is no virtual file added, to avoid invalidating the form for nothing
        $isUpdatingFile = !empty($data['has_file']) && !empty($data['virtual_product_file_id']) && null === $data['file'];
        $isAddingFile = !empty($data['has_file']) && empty($data['virtual_product_file_id']);

        if ($isAddingFile) {
            // when new file is being added we leave all constraints unchanged
            return;
        }

        if ($isUpdatingFile) {
            // when existing file is being updated we do not require uploading a file (remove file NotBlank constraints),
            // but leave constraints for other updatable fields
            $form->add($this->formCloner->cloneForm($form->get('file'), ['constraints' => []]));

            return;
        }

        // when existing file is being deleted or file is not being added (has_file is falsy) we remove all constraints
        $form->add($this->formCloner->cloneForm($form->get('file'), ['constraints' => []]));
        $form->add($this->formCloner->cloneForm($form->get('name'), ['constraints' => []]));
        $form->add($this->formCloner->cloneForm($form->get('access_days_limit'), ['constraints' => []]));
        $form->add($this->formCloner->cloneForm($form->get('download_times_limit'), ['constraints' => []]));
    }
}
