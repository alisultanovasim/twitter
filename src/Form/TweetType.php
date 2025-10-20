<?php

namespace App\Form;

use App\Entity\Tweet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\FileType;



class TweetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'What\'s happening?',
                'attr' => [
                    'rows' => 4,
                    'maxlength' => 280,
                    'placeholder' => 'Tweet something...'
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Image (optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '25M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Yalnız JPG, PNG, GIF və WEBP formatları qəbul olunur',
                        'maxSizeMessage' => 'Maksimum fayl ölçüsü 5MB',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tweet::class,
        ]);
    }
}