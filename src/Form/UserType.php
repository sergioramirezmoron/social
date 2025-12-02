<?php

namespace App\Form;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
class UserType extends AbstractType
{
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('picture', FileType::class, [
                    'label' => 'Image (jpg, png, gif)',
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new File(
                            maxSize: '2048k',
                            mimeTypes: ['image/*'],
                            mimeTypesMessage: 'Introduzca una imagen válida',
                        )
                    ],
                ])
                ->add('username')
                ->add('isPrivated', CheckboxType::class, [
                    'label' => 'Cuenta Privada',
                    'required' => false,
                ])
            ;
        }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
