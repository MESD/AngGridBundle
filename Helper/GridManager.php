<?php

namespace MESD\Ang\GridBundle\Helper;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;

class GridManager
{
    private $controller;
    private $export;
    private $grid;
    private $queryBuilder;
    private $root;

    public function __construct($root, $queryBuilder, $request, $exportType, $controller)
    {
        $this->controller = $controller;
        $this->queryBuilder = $queryBuilder;
        $this->root = $root;

        $this->grid['exportString'] = $request->query->get( 'exportString' );
        $this->grid['headers'] = array();
        $this->grid['page'] = $request->query->get( 'page' );
        $this->grid['perPage'] = $request->query->get( 'perPage' );
        $this->grid['requestCount'] = $request->query->get( 'requestCount' );
        $this->grid['search'] = $request->query->get( 'search' );
        $this->grid['sortsString'] = $request->query->get( 'sorts' );

        if ( is_null( $exportType ) ) {
            $this->grid['exportType'] = $request->query->get( 'exportType' );
        } else {
            $this->grid['exportType'] = $exportType;
        }

        if ( is_null( $this->grid['exportString'] ) ) {
            $this->export = false;
        } else {
            $this->export = true;
        }
    }

    public function setAction($item)
    {
        $alias = $item['alias'];
        if (!isset($item['class'])) {
            $item['class'] = 'btn-mini action btn-default';
        }
        if (!isset($item['icon'])) {
            $item['icon'] = 'icon-search';
        }
        if (!isset($item['title'])) {
            $item['title'] = $alias;
        }
        $this->grid['actions'][$item['alias']] = $item;
    }

    public function setHeader($item)
    {
        $name = $item['column'];
        if (!isset($item['header'])) {
            if (isset($item['title'])) {
                $item['header'] = $item['title'];
            } else {
                $item['header'] = $name;
            }
        }
        if (!isset($item['id'])) {
            $item['id'] = str_replace('.', '-', $name);
        }
        if (!isset($item['searchable'])) {
            $item['searchable'] = 'true';
        }
        if (!isset($item['sort-icon'])) {
            $item['sort-icon'] = 'icon-sort';
        }
        if (!isset($item['title'])) {
            if (isset($item['header'])) {
                $item['title'] = $item['header'];
            } else {
                $item['title'] = $name;
            }
        }
        $this->grid['headers'][$item['column']] = $item;
    }

    public function getJsonResponse()
    {
        $this->queryBuilder->select($this->queryBuilder->expr()->count('distinct ' . $this->root . '.id'));
        $this->grid['total'] = $this->queryBuilder->getQuery()->getSingleScalarResult();
        $qb = Query::search( $this->queryBuilder, $this->grid['search'], $this->grid['headers'] );
        $this->grid['filtered'] = $qb->getQuery()->getSingleScalarResult();
        $this->queryBuilder->select($this->root);

        if (!$this->export) {
            if ( 0 < $this->grid['filtered'] ) {
                $this->grid['last'] = ceil( $this->grid['filtered'] / $this->grid['perPage'] );
            } else {
                $this->grid['last'] = 1;
            }
            if ( 1 > $this->grid['page'] ) {
                $this->grid['page'] = 1;
            } elseif ( $this->grid['last'] < $this->grid['page'] ) {
                $this->grid['page'] = $this->grid['last'];
            }
            $qb->setFirstResult( $this->grid['perPage'] * ( $this->grid['page'] - 1 ) )
            ->setMaxResults( $this->grid['perPage'] );
        }

        if (!is_null( $this->grid['sortsString'])) {
            $this->grid['sorts'] = json_decode( $this->grid['sortsString'] );
            foreach ($this->grid['sorts'] as $sort) {
                $qb->addOrderBy( $this->grid['headers'][$sort->column]['column'], $sort->direction );
                if ('asc' == $sort->direction) {
                    $this->grid['headers'][$sort->column]['sortIcon'] = 'icon-sort-up';
                } else {
                    $this->grid['headers'][$sort->column]['sortIcon'] = 'icon-sort-down';
                }
            }
        }

        $results = new Paginator( $qb->getQuery(), $fetchJoinCollection = true );

        $this->grid['entities'] = array();

        foreach($results as $result) {
            $paths = array();
            foreach($this->grid['actions'] as $action) {
                $paths[$action['alias']] = $this->controller->generateUrl($action['alias'], array( 'id' => $result->getId()));
            }
            $values = array();
            foreach($this->grid['headers'] as $header) {
                $columns = explode('.', $header['column']);
                $value = $result;
                foreach($columns as $key => $column){
                    if ($key > 0) {
                        $value = call_user_func(array($value,'get' . ucwords($column)));
                    }
                }
                $values[$header['column']] = $value;
            }
            $this->grid['entities'][] = array(
                'paths' => $paths,
                'values' => $values,
            );
        }

        if ( $this->export ) {
            $response = $this->render('MESDAngGridBundle:Grid:export.' . $this->grid['exportType'] . '.twig',
                array(
                    'entities' => $this->grid['entities'],
                    'headers' => $this->grid['headers'],
                )
            );
            $response->headers->set('Content-Type', 'text/' . $this->grid['exportType']);
            $response->headers->set('Content-Disposition', 'attachment; filename="export.' . $this->grid['exportType'] . '"');

            return $response;
        }

        // export

        return new JsonResponse($this->grid);
    }
}