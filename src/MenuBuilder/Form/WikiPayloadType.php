<?php

declare(strict_types=1);

namespace Citadel\Wiki\MenuBuilder\Form;

use Citadel\Wiki\Repository\WikiRepository;
use Citadel\Wiki\Widget\WikiChoices;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class WikiPayloadType extends AbstractType
{
    public function __construct(private readonly WikiRepository $wikiRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('wiki', ChoiceType::class, [
            'label' => 'wiki.menu_builder.wiki',
            'choices' => WikiChoices::from($this->wikiRepository),
            'placeholder' => 'wiki.menu_builder.select_wiki',
            'constraints' => [new NotBlank()],
        ]);
    }
}
