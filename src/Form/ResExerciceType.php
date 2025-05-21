<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ResExerciceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $blocsByType = $options['blocs_by_type'];

        foreach ($blocsByType as $typeId => $choices) {
            $builder->add($typeId, ChoiceType::class, [
                'label' => "$typeId",
                'choices' => array_flip($choices), // Symfony attend [ "valeur" => id ]
                'expanded' => false,  // Menu déroulant
                'multiple' => false,  // Choix unique
                'required' => true,
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Soumettre',
            'attr' => ['class' => 'btn btn-primary']
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'blocs_by_type' => [], // On passe les blocs ici depuis le contrôleur
        ]);
    }
}
