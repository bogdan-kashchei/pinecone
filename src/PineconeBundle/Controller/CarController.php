<?php

namespace App\PineconeBundle\Controller;

use App\PineconeBundle\Service\PineconeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CarController extends AbstractController
{
    #[Route('/car/register', name: 'car_register')]
    public function register(Request $request, PineconeClient $pineconeClient): Response
    {
        $form = $this->createFormBuilder()
            ->add('vin', TextType::class, ['label' => 'VIN'])
            ->add('name', TextType::class, ['label' => 'Name'])
            ->add('year', TextType::class, ['label' => 'Year'])
            ->add('type', TextType::class, ['label' => 'Type'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'data' => ''])
            ->add('image', FileType::class, ['label' => 'Car Image', 'required' => false, 'data' => ''])
            ->add('register', SubmitType::class, ['label' => 'Register'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = array_map(function ($value) {
                return $value === null ? '' : $value;
            }, (array)$form->getData());
            $index = $pineconeClient->getIndex('vehicle');
            $embedding = $pineconeClient->createEmbedding(json_encode($data) ?: '');
            $car = [
                [
                    'id' => $data['vin'],
                    'values' => $embedding,
                    'metadata' => $data,
                ]
            ];

            if (isset($index['host']) && is_string($index['host'])) {
                $pineconeClient->upsertVectors($index['host'], $car, 'car_namespace');
            }

            return $this->redirectToRoute('car_register');
        }

        return $this->render('car/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/car/search', name: 'car_search')]
    public function search(Request $request, PineconeClient $pineconeClient): Response
    {
        $index = $pineconeClient->getIndex('vehicle');
        $form = $this->createFormBuilder()
            ->add('query', TextType::class, ['label' => 'Search'])
            ->add('image', FileType::class, ['label' => 'Search by Image', 'required' => false])
            ->add('search', SubmitType::class, ['label' => 'Search'])
            ->getForm();

        $form->handleRequest($request);
        $results = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $data = (array)$form->getData();
            // Handle text search
            $searchTerm = $data['query'] ?? '';
            if (!is_string($searchTerm)) {
                throw new \RuntimeException('Search term must be a string.');
            }
            $embedding = $pineconeClient->createEmbedding($searchTerm);

            if (isset($index['host']) && is_string($index['host'])) {
                $results = $pineconeClient->searchIndex($index['host'], $embedding, 'car_namespace', [], 1);
            }
        }

        return $this->render('car/search.html.twig', [
            'form' => $form->createView(),
            'results' => $results,
        ]);
    }
}