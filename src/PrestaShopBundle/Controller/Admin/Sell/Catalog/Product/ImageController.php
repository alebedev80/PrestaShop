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

namespace PrestaShopBundle\Controller\Admin\Sell\Catalog\Product;

use Exception;
use PrestaShop\PrestaShop\Adapter\Shop\Repository\ShopRepository;
use PrestaShop\PrestaShop\Core\Domain\Exception\FileUploadException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\DeleteProductImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\ProductImageSetting;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\SetProductImagesForAllShopCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\CannotAddProductImageException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\CannotRemoveCoverException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Exception\ProductImageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetProductImage;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetProductImages;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetShopProductImages;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\ProductImage;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\Shop\ShopImageAssociation;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\QueryResult\Shop\ShopProductImagesCollection;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;
use PrestaShop\PrestaShop\Core\Exception\CoreException;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Builder\FormBuilderInterface;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Handler\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Image\Exception\CannotUnlinkImageException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\MemoryLimitException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\UploadedImageConstraintException;
use PrestaShop\PrestaShop\Core\Image\Uploader\Exception\UploadedImageSizeException;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends PrestaShopAdminController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            ShopRepository::class => ShopRepository::class,
        ]);
    }

    /**
     * Retrieves images for all shops, but the cover (which is multi-shop compatable) is retrieved based on $shopId
     *
     * @param int $productId
     * @param int $shopId
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to update this.')]
    public function getImagesForShopAction(int $productId, int $shopId): JsonResponse
    {
        /** @var ProductImage[] $images */
        $images = $this->dispatchQuery(new GetProductImages(
            $productId,
            ShopConstraint::shop($shopId)
        ));

        return $this->json(array_map([$this, 'formatImage'], $images));
    }

    /**
     * @param int $productId
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller')) || is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to red or update this.')]
    public function productShopImagesAction(int $productId, Request $request): JsonResponse
    {
        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                $imageAssociations = json_decode($request->request->get('image_associations'), true);
                $command = new SetProductImagesForAllShopCommand($productId);
                foreach ($imageAssociations as $imageAssociation) {
                    $command->addProductSetting(new ProductImageSetting(
                        $imageAssociation['imageId'],
                        $imageAssociation['shops']
                    ));
                }
                $this->dispatchQuery($command);
            } catch (CoreException $e) {
                return $this->json([
                    'status' => false,
                    'message' => $this->getErrorMessageForException($e, $this->getErrorMessages($e)),
                ]);
            }
        }

        return $this->getProductShopImagesJsonResponse($productId);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to update this.')]
    public function addImageAction(
        Request $request,
        #[Autowire(service: 'prestashop.core.form.identifiable_object.builder.product_image_form_builder')]
        FormBuilderInterface $productImageFormBuilder,
        #[Autowire(service: 'prestashop.core.form.identifiable_object.product_image_form_handler')]
        FormHandlerInterface $productImageFormHandler
    ): JsonResponse {
        $imageForm = $productImageFormBuilder->getForm();
        $imageForm->handleRequest($request);

        try {
            $result = $productImageFormHandler->handle($imageForm);

            if (!$result->isSubmitted() || !$result->isValid()) {
                return new JsonResponse([
                    'error' => 'Invalid form data.',
                    'form_errors' => $this->getFormErrorsForJS($imageForm),
                ], Response::HTTP_BAD_REQUEST);
            }
            if (null === $result->getIdentifiableObjectId()) {
                return new JsonResponse([
                    'error' => 'Could not create image.',
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => $this->getErrorMessageForException($e, $this->getErrorMessages($e)),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->getProductImageJsonResponse($result->getIdentifiableObjectId());
    }

    /**
     * @param Request $request
     * @param int $productImageId
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to update this.')]
    public function updateImageAction(
        Request $request,
        int $productImageId,
        #[Autowire(service: 'prestashop.core.form.identifiable_object.builder.product_image_form_builder')]
        FormBuilderInterface $productImageFormBuilder,
        #[Autowire(service: 'prestashop.core.form.identifiable_object.product_image_form_handler')]
        FormHandlerInterface $productImageFormHandler
    ): JsonResponse {
        $imageForm = $productImageFormBuilder->getFormFor($productImageId, [], [
            'method' => $request->getMethod(),
        ]);
        $imageForm->handleRequest($request);

        try {
            $result = $productImageFormHandler->handleFor($productImageId, $imageForm);

            if (!$result->isSubmitted() || !$result->isValid()) {
                return new JsonResponse([
                    'error' => 'Invalid form data.',
                    'form_errors' => $this->getFormErrorsForJS($imageForm),
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => $this->getErrorMessageForException($e, $this->getErrorMessages($e)),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->getProductImageJsonResponse($productImageId);
    }

    /**
     * @param int $productImageId
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message: 'You do not have permission to update this.')]
    public function deleteImageAction(int $productImageId): JsonResponse
    {
        try {
            $this->dispatchCommand(new DeleteProductImageCommand($productImageId));
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => $this->getErrorMessageForException($e, $this->getErrorMessages($e)),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getProductShopImagesJsonResponse(int $productId): JsonResponse
    {
        /** @var ShopProductImagesCollection $shopImages */
        $shopImages = $this->dispatchQuery(new GetShopProductImages($productId));

        return new JsonResponse($this->formatShopImages($shopImages));
    }

    /**
     * @param int $productImageId
     *
     * @return JsonResponse
     */
    private function getProductImageJsonResponse(int $productImageId): JsonResponse
    {
        $productImage = $this->dispatchQuery(new GetProductImage(
            $productImageId,
            ShopConstraint::shop($this->getShopContext()->getId())
        ));

        return $this->json($this->formatImage($productImage));
    }

    /**
     * @param ProductImage $image
     *
     * @return array<string, mixed>
     */
    private function formatImage(ProductImage $image): array
    {
        return [
            'image_id' => $image->getImageId(),
            'is_cover' => $image->isCover(),
            'position' => $image->getPosition(),
            'image_url' => $image->getImageUrl(),
            'thumbnail_url' => $image->getThumbnailUrl(),
            'legends' => $image->getLocalizedLegends(),
            'shop_ids' => $image->getShopIds(),
        ];
    }

    /**
     * @param ShopProductImagesCollection $shopImagesCollection
     *
     * @return array<int, array{shopId: int, shopName: string, images: array<int, array{imageId: int, isCover: bool}>}>
     */
    private function formatShopImages(ShopProductImagesCollection $shopImagesCollection): array
    {
        $shopRepository = $this->container->get(ShopRepository::class);
        $formattedShopsImages = [];
        foreach ($shopImagesCollection as $shopProductImage) {
            $shopImages = [
                'shopId' => $shopProductImage->getShopId(),
                'shopName' => $shopRepository->getShopName(new ShopId($shopProductImage->getShopId())),
                'images' => [],
            ];

            /** @var ShopImageAssociation $shopImageAssociation */
            foreach ($shopProductImage->getProductImages() as $shopImageAssociation) {
                $shopImages['images'][] = [
                    'imageId' => $shopImageAssociation->getImageId(),
                    'isCover' => $shopImageAssociation->isCover(),
                ];
            }

            $formattedShopsImages[] = $shopImages;
        }

        return $formattedShopsImages;
    }

    /**
     * Gets an error by exception class and its code.
     *
     * @param Exception $e
     *
     * @return array
     */
    private function getErrorMessages(Exception $e): array
    {
        $messages = [
            ProductConstraintException::class => [
                ProductConstraintException::INVALID_ID => $this->trans(
                    'Invalid ID.',
                    [],
                    'Admin.Notifications.Error'
                ),
            ],
            ProductImageNotFoundException::class => $this->trans(
                'The object cannot be loaded (or found).',
                [],
                'Admin.Notifications.Error'
            ),
            UploadedImageConstraintException::class => [
                UploadedImageConstraintException::UNRECOGNIZED_FORMAT => $this->trans(
                    'Image format not recognized, allowed formats are: .gif, .jpg, .png',
                    [],
                    'Admin.Notifications.Error'
                ),
            ],
            MemoryLimitException::class => $this->trans(
                'Due to memory limit restrictions, this image cannot be loaded. Please increase your memory_limit value via your server\'s configuration settings.',
                [],
                'Admin.Notifications.Error'
            ),
            CannotAddProductImageException::class => $this->trans(
                'An error occurred while attempting to save.',
                [],
                'Admin.Notifications.Error'
            ),
            FileUploadException::class => [
                UPLOAD_ERR_NO_FILE => $this->trans(
                    'No file was uploaded.',
                    [],
                    'Admin.Notifications.Error'
                ),
            ],
            CannotUnlinkImageException::class => $this->trans(
                'Cannot delete file',
                [],
                'Admin.Notifications.Error'
            ),
            CannotRemoveCoverException::class => $this->trans(
                'Cannot remove cover image.',
                [],
                'Admin.Notifications.Error'
            ),
        ];

        if ($e instanceof UploadedImageSizeException) {
            $messages[UploadedImageSizeException::class] = $this->trans(
                'Max file size allowed is "%s" bytes.',
                [$e->getAllowedSizeBytes()],
                'Admin.Notifications.Error'
            );
        }

        return $messages;
    }
}
