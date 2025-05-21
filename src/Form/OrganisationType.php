<?php

namespace App\Form;

use App\Entity\Organisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, [
                'label' => 'Nom de l\'organisation',
                'attr' => [
                    'placeholder' => 'Entrez le nom de l\'organisation',
                ],
            ])
            ->add('licences', null, [
                'label' => 'Nombre de licences',
                'attr' => [
                    'placeholder' => 'Entrez le nombre de licences',
                ],
                'data' => 0,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer l\'organisation',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organisation::class,
        ]);
    }
}
