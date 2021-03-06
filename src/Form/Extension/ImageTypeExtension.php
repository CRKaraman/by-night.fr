<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Extension;

use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventProfilePicture;
use App\Picture\UserProfilePicture;
use App\Twig\AssetExtension;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Vich\UploaderBundle\Storage\StorageInterface;

class ImageTypeExtension extends AbstractTypeExtension
{
    private StorageInterface $storage;

    private AssetExtension $assetExtension;

    private UserProfilePicture $userProfilePicture;

    private EventProfilePicture $eventProfilePicture;

    public function __construct(StorageInterface $storage, AssetExtension $assetExtension, UserProfilePicture $userProfilePicture, EventProfilePicture $eventProfilePicture)
    {
        $this->storage = $storage;
        $this->assetExtension = $assetExtension;
        $this->userProfilePicture = $userProfilePicture;
        $this->eventProfilePicture = $eventProfilePicture;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $object = $form->getParent()->getData();
        $view->vars['image_thumb_uri'] = null;
        $view->vars['image_thumb_uri_retina'] = null;
        $view->vars['image_thumb_params'] = $options['thumb_params'];

        if (null !== $object) {
            if ($object instanceof Event) {
                $view->vars['download_uri'] = $this->eventProfilePicture->getOriginalPicture($object);
                $view->vars['image_thumb_uri'] = $this->eventProfilePicture->getPicture($object, $options['thumb_params']);
                $view->vars['image_thumb_uri_retina'] = $this->eventProfilePicture->getPicture($object, $options['thumb_params'] + ['dpr' => 2]);
            } elseif ($object instanceof User) {
                $view->vars['download_uri'] = $this->userProfilePicture->getOriginalProfilePicture($object);
                $view->vars['image_thumb_uri'] = $this->userProfilePicture->getProfilePicture($object, $options['thumb_params']);
                $view->vars['image_thumb_uri_retina'] = $this->userProfilePicture->getProfilePicture($object, $options['thumb_params'] + ['dpr' => 2]);
            } else {
                $path = $this->storage->resolveUri($object, $form->getName(), null);
                if (null !== $path) {
                    $view->vars['image_thumb_uri'] = $this->assetExtension->thumbAsset($path, $options['thumb_params']);
                    $view->vars['image_thumb_uri_retina'] = $this->assetExtension->thumbAsset($object, $options['thumb_params'] + ['dpr' => 2]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
        $resolver->setRequired(['thumb_params']);
        $resolver->setAllowedTypes('thumb_params', 'array');
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        yield VichImageType::class;
    }
}
