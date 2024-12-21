<?php

namespace App\PineconeBundle\Controller;

use App\PineconeBundle\Service\PineconeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    /**
     * @param Request $request
     * @param PineconeClient $pineconeClient
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    #[Route('/search', name: 'search')]
    public function search(Request $request, PineconeClient $pineconeClient): Response
    {
        $form = $this->createFormBuilder()
            ->add('query', TextType::class, ['label' => 'Search'])
            ->add('search', SubmitType::class, ['label' => 'Search'])
            ->getForm();

        $form->handleRequest($request);
        $results = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!is_array($data) || !isset($data['query']) || !is_string($data['query'])) {
                throw new \RuntimeException('Invalid form data.');
            }
            $searchTerm = $data['query'];
            $embeddings = $pineconeClient->createEmbedding($searchTerm);

            $index = $pineconeClient->getIndex('test');
            if (!isset($index['host']) || !is_string($index['host'])) {
                throw new \RuntimeException('Invalid index data.');
            }

            $describedIndex = $pineconeClient->describeIndexStats($index['host']);
            if (!isset($describedIndex['namespaces']) || !is_array($describedIndex['namespaces'])) {
                throw new \RuntimeException('Invalid described index data.');
            }

            $namespace = array_key_first($describedIndex['namespaces']);
            if (!is_string($namespace)) {
                throw new \RuntimeException('Invalid namespace.');
            }

            $results = $pineconeClient->searchIndex($index['host'], $embeddings, $namespace);
        }

        return $this->render('search/index.html.twig', [
            'form' => $form->createView(),
            'results' => $results,
        ]);
    }
}