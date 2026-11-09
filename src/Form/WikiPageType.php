<?php

declare(strict_types=1);

namespace Citadel\Wiki\Form;

use Citadel\Wiki\Form\DTO\WikiPageData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<WikiPageData>
 */
class WikiPageType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WikiPageData::class]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'wiki.edit.page_title',
                'empty_data' => '',
                'attr' => ['maxlength' => 255],
            ])
            ->add('parent', TextType::class, [
                'label' => 'wiki.edit.parent',
                'required' => false,
                'attr' => ['placeholder' => 'wiki.edit.parent_placeholder'],
            ])
            ->add('content', TextareaType::class, [
                'label' => false,
                'empty_data' => '',
                'required' => false,
                'attr' => [
                    'rows' => 24,
                    'class' => 'w-100 wiki-source',
                    'aria-label' => 'wiki.edit.content',
                    'data-citadel--wiki-plugin--editor-target' => 'source',
                ],
            ])
            ->add('summary', TextType::class, [
                'label' => 'wiki.edit.summary',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('minor', CheckboxType::class, [
                'label' => 'wiki.edit.minor',
                'required' => false,
            ])
            ->add('base', HiddenType::class);
    }
}
