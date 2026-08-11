<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\QuotationCatalogItem;
use App\Entity\QuotationLine;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class QuotationLineForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var QuotationCatalogItem[] $catalogItems */
        $catalogItems = $options['catalog_items'];
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($catalogItems): void {
            $data = $event->getData();
            if (!\is_array($data) || empty($data['catalogItem']) || (!\is_int($data['catalogItem']) && !\is_string($data['catalogItem']))) {
                return;
            }

            $catalogId = (string) $data['catalogItem'];

            foreach ($catalogItems as $item) {
                if (!$item instanceof QuotationCatalogItem || (string) $item->getId() !== $catalogId) {
                    continue;
                }

                if (empty($data['description'])) {
                    $data['description'] = $item->getDescription() ?: $item->getName();
                }
                if (empty($data['unit'])) {
                    $data['unit'] = $item->getUnit();
                }
                if (empty($data['unitPrice'])) {
                    $data['unitPrice'] = $item->getDefaultPrice();
                }
                $event->setData($data);
                break;
            }
        });

        $builder
            ->add('catalogItem', EntityType::class, [
                'class' => QuotationCatalogItem::class,
                'choice_label' => 'name',
                'choices' => $options['catalog_items'],
                'required' => false,
                'placeholder' => 'quotation.catalog_item_placeholder',
                'label' => 'quotation.catalog_item',
                'choice_attr' => static function (?QuotationCatalogItem $item): array {
                    if ($item === null) {
                        return [];
                    }

                    return [
                        'data-description' => $item->getDescription() ?: $item->getName(),
                        'data-unit' => $item->getUnit(),
                        'data-unit-price' => $item->getDefaultPrice(),
                    ];
                },
            ])
            ->add('description', TextType::class, ['label' => 'quotation.description'])
            ->add('unit', TextType::class, ['label' => 'quotation.unit'])
            ->add('quantity', NumberType::class, ['scale' => 4, 'html5' => true, 'label' => 'quotation.quantity'])
            ->add('unitPrice', MoneyType::class, ['currency' => false, 'scale' => 4, 'html5' => true, 'label' => 'quotation.unit_price']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuotationLine::class,
            'catalog_items' => [],
        ]);
        $resolver->setAllowedTypes('catalog_items', 'array');
    }
}
