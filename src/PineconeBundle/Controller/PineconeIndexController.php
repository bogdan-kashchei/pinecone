<?php

namespace App\PineconeBundle\Controller;

use App\PineconeBundle\Service\PineconeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PineconeIndexController extends AbstractController
{
    /**
     * @param PineconeClient $pineconeClient
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    #[Route('/pinecone/index', name: 'pinecone_index')]
    public function index(PineconeClient $pineconeClient): Response
    {
        $indexes = $pineconeClient->getIndexes();

        return $this->render('pinecone/index.html.twig', [
            'indexes' => $indexes,
        ]);
    }

    #[Route('/pinecone/create', name: 'pinecone_create')]
    public function create(Request $request, PineconeClient $pineconeClient): Response
    {
        $form = $this->createFormBuilder()
            ->add('indexName', TextType::class, ['label' => 'Index Name'])
            ->add('dimension', TextType::class, ['label' => 'Dimension'])
            ->add('metric', TextType::class, ['label' => 'Metric'])
            ->add('cloud', TextType::class, ['label' => 'Cloud'])
            ->add('region', TextType::class, ['label' => 'Region'])
            ->add('create', SubmitType::class, ['label' => 'Create'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!is_array($data)) {
                throw new \RuntimeException('Form data must be an array.');
            }
            $dimension = $data['dimension'];
            if (!is_numeric($dimension)) {
                throw new \RuntimeException('Dimension must be a numeric value.');
            }
            $indexName = $data['indexName'];
            $metric = $data['metric'];
            $cloud = $data['cloud'];
            $region = $data['region'];

            $payload = [
                'name' => $indexName,
                'dimension' => $dimension,
                'metric' => $metric,
                'spec' => [
                    'serverless' => [
                        'cloud' => $cloud,
                        'region' => $region,
                    ],
                ],
                'deletion_protection' => 'disabled',
            ];

            $pineconeClient->createIndex($payload);
            return $this->redirectToRoute('pinecone_index');
        }

        return $this->render('pinecone/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/pinecone/edit/{indexName}/upsert', name: 'pinecone_upsert')]
    public function upsert(Request $request, PineconeClient $pineconeClient, string $indexName): Response
    {
        $index = $pineconeClient->getIndex($indexName);
        if (!isset($index['host']) || !is_string($index['host'])) {
            throw new \RuntimeException('Invalid index data.');
        }
        $describedIndex = $pineconeClient->describeIndexStats($index['host']);
        if (!isset($describedIndex['namespaces']) || !is_array($describedIndex['namespaces'])) {
            throw new \RuntimeException('Invalid described index data.');
        }
        $namespace = array_keys($describedIndex['namespaces'])[0] ?? '';
        $form = $this->createFormBuilder()
            ->add('indexHost', TextType::class, ['label' => 'Index Host', 'data' => $index['host']])
            ->add('namespace', TextType::class, ['label' => 'Namespace', 'data' => $namespace])
            ->add('vectors', TextareaType::class, ['label' => 'Vectors (JSON format)'])
            ->add('upsert', SubmitType::class, ['label' => 'Upsert Vectors'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!is_array($data)) {
                throw new \RuntimeException('Form data must be an array.');
            }

            $indexHost = $data['indexHost'] ?? null;
            $namespace = $data['namespace'] ?? null;
            $vectorsRaw = $data['vectors'] ?? '';

            // Ensure input types are valid
            if (!is_string($indexHost) || !is_string($namespace) || !is_string($vectorsRaw)) {
                throw new \RuntimeException('Invalid input data.');
            }

            // Decode JSON string safely
            $vectors = json_decode($vectorsRaw, true);

            // Validate decoded JSON
            if (!is_array($vectors)) {
                throw new \RuntimeException('Vectors must be a valid JSON array.');
            }

            // Ensure vectors match the expected structure: array<int, array<string, mixed>>
            foreach ($vectors as $key => $vector) {
                if (!is_int($key) || !is_array($vector)) {
                    throw new \RuntimeException('Vectors must be an array of associative arrays.');
                }
            }

            // Call the Pinecone client with validated data
            $pineconeClient->upsertVectors($indexHost, $vectors, $namespace);

            return $this->redirectToRoute('pinecone_index');
        }

        return $this->render('pinecone/upsert.html.twig', [
            'form' => $form->createView(),
            'indexName' => $indexName,
        ]);
    }

    #[Route('/pinecone/view/{indexName}', name: 'pinecone_view')]
    public function view(string $indexName, PineconeClient $pineconeClient): Response
    {
        $indexDetails = $pineconeClient->getIndex($indexName);

        return $this->render('pinecone/view.html.twig', [
            'indexDetails' => $indexDetails,
        ]);
    }

    #[Route('/pinecone/delete/{indexName}', name: 'pinecone_delete')]
    public function delete(PineconeClient $pineconeClient, string $indexName): Response
    {
        $pineconeClient->deleteIndex($indexName);
        return $this->redirectToRoute('pinecone_index');
    }
}