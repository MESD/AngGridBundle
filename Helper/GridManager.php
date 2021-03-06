<?php

namespace MESD\Ang\GridBundle\Helper;

use Knp\Component\Pager\Paginator;
use Knp\Bundle\SnappyBundle\Snappy\LoggableGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\Query\Expr;

class GridManager {
    private $controller;
    private $export;
    private $exportAlias;
    private $exportParams;
    private $grid;
    private $queryBuilder;
    private $root;
    private $rootClass;
    private $selects;
    private $prepend;
    private $snappy;
    private $debug;
    private $perPageList;

    public function __construct( $controller, Paginator $paginator, LoggableGenerator $snappy = null ) {
        $this->controller = $controller;
        $this->entityManager = $this->controller->getDoctrine()->getManager();
        $this->paginator = $paginator;
        $this->request = $this->controller->getRequest();
        $this->snappy = $snappy;
        $this->prepend = '';

        $this->selects = array();
        $this->grid = array();
        $this->perPageList = array( 10, 25, 50, 100 );

        $this->exportParams = array();

        $this->debug = $this->request->query->get( 'debug' );

        $this->grid['paths'] = array();
        $this->grid['entities'] = array();
        $this->grid['buttons'] = array();
        $this->grid['entities'] = array();
        $this->grid['exportString'] = $this->request->query->get( 'exportString' );
        $this->grid['headers'] = array();
        $this->grid['requestCount'] = $this->request->query->get( 'requestCount' );
        $this->grid['exportArray'] = is_null( $snappy )
            ? array(
            array( 'label' => 'TSV', 'value' => 'tsv', 'exportLink' => '#' ),
            array( 'label' => 'CSV', 'value' => 'csv', 'exportLink' => '#' ),
            array( 'label' => 'Excel', 'value' => 'xls', 'exportLink' => '#' ) )
            : array(
            array( 'label' => 'TSV', 'value' => 'tsv', 'exportLink' => '#' ),
            array( 'label' => 'CSV', 'value' => 'csv', 'exportLink' => '#' ),
            array( 'label' => 'Excel', 'value' => 'xls', 'exportLink' => '#' ),
            array( 'label' => 'PDF', 'value' => 'pdf', 'exportLink' => '#' ) );

        if ( is_null( $this->grid['exportString'] ) ) {
            $this->export = false;
        } else {
            $this->export = true;
        }

        $grid0 = $this->request->cookies->get( 'grid0' );
        if ( isset( $grid0 ) ) {
            $cookie =  json_decode( $grid0 );
        }

        $addView = $this->request->query->get( 'addView' );
        if ( isset( $addView ) ) {
            $this->grid['addView'] = $addView;
        } elseif ( isset( $cookie->addView ) ) {
            $this->grid['addView'] = $cookie->addView;
        } else {
            $this->grid['addView'] = false;
        }

        $filters = json_decode( $this->request->query->get( 'filters' ) );
        if ( isset( $filters ) ) {
            $this->grid['filters'] = $filters;
        } elseif ( isset( $cookie->filters ) ) {
            $this->grid['filters'] = $cookie->filters;
        } else {
            $this->grid['filters'] = null;
        }

        $page = $this->request->query->get( 'page' );
        if ( isset( $page ) ) {
            $this->grid['page'] = $page;
        } elseif ( isset( $cookie->page ) ) {
            $this->grid['page'] = $cookie->page;
        } else {
            $this->grid['page'] = null;
        }

        $perPage = $this->request->query->get( 'perPage' );
        if ( isset( $perPage ) ) {
            $this->grid['perPage'] = $perPage;
        } elseif ( isset( $cookie->perPage ) ) {
            $this->grid['perPage'] = $cookie->perPage;
        } else {
            $this->grid['perPage'] = null;
        }

        $search = $this->request->query->get( 'search' );
        if ( isset( $search ) ) {
            $this->grid['search'] = $search;
        } elseif ( isset( $cookie->search ) ) {
            $this->grid['search'] = $cookie->search;
        } else {
            $this->grid['search'] = null;
        }

        $showControl = $this->request->query->get( 'showControl' );
        if ( isset( $showControl ) ) {
            $this->grid['showControl'] = $showControl;
        } elseif ( isset( $cookie->showControl ) ) {
            $this->grid['showControl'] = $cookie->showControl;
        } else {
            $this->grid['showControl'] = true;
        }

        $sorts = json_decode( $this->request->query->get( 'sorts' ) );
        if ( isset( $sorts ) ) {
            $this->grid['sorts'] = $sorts;
        } elseif ( isset( $cookie->sorts ) ) {
            $this->grid['sorts'] = $cookie->sorts;
        } else {
            $this->grid['sorts'] = null;
        }
    }


    // setRoot is deprecated (it happens on construct now)
    public function setRoot( $root, $rootClass ) {
        // $this->root = $root;
        // $this->rootClass = $rootClass;
    }

    public function setQueryBuilder( $queryBuilder ) {
        $this->queryBuilder = $queryBuilder;
        $alias = $queryBuilder->getDQLPart( 'from' );
        $this->root = $alias[0]->getAlias();
        // $this->root=$queryBuilder->getDQLPart( 'from' )[0]->getAlias();
        $from = $queryBuilder->getDQLPart( 'from' );
        $this->rootClass = $alias[0]->getFrom();
        // $this->rootClass=$queryBuilder->getDQLPart( 'from' )[0]->getFrom();
    }

    public function setExportType( $exportType ) {
        if ( is_null( $exportType ) ) {
            $this->grid['exportType'] = $this->request->query->get( 'exportType' );
        } else {
            $this->grid['exportType'] = $exportType;
        }

    }
    // setSelect is deprecated (it happens in JSON respone now)
    public function setSelect( $select ) {
        // $this->selects[$select] = $select;
    }

    public function setPath( $item ) {
        $alias = $item['alias'];

        if ( !isset( $item['class'] ) ) {
            $item['class'] = 'btn btn-mini action grid '.( isset( $item['button'] ) ? $item['button'] :'btn-default' ) ;
        }

        if ( !isset( $item['icon'] ) ) {
            $item['icon'] = 'icon-search';
        }

        // title_function overrides default
        if ( !isset( $item['title'] ) && !isset( $item['title_function'] ) ) {
            $item['title'] = $alias;
        }

        $this->grid['paths'][$item['alias']] = $item;
    }

    public function setButton( $item ) {
        $alias = $item['alias'];

        if ( !isset( $item['class'] ) ) {
            $item['class'] = 'btn btn-mini btn-default action grid';
        }

        if ( !isset( $item['icon'] ) ) {
            $item['icon'] = 'icon-search';
        }

        // title_function overrides default
        if ( !isset( $item['title'] ) && !isset( $item['title_function'] ) ) {
            $item['title'] = $alias;
        }

        $this->grid['buttons'][$item['alias']] = $item;
    }

    public function setExportAlias( $alias, $params = array() ) {
        $this->exportAlias = $alias;
        $this->exportParams = $params;
    }

    public function setHeader( $item ) {
        if ( !isset( $item['field'] ) ) {
            $name = $item['title'];
            $item['field'] = $name;
            $item['column'] = $name;
        } else {
            $name = $item['field'];
            if ( !isset( $item['column'] ) ) {
                $last = strrpos( $name, '.' );
                if ( false == $last ) {
                    $item['column'] = $name;
                } else {
                    $nextLast = strrpos( $name, '.', $last - strlen( $name ) - 1 );
                    if ( false == $nextLast ) {
                        $item['column'] = $name;
                    } else {
                        $item['column'] = substr( $name, $nextLast + 1 );
                    }
                }
            }
        }

        if ( isset( $item['align'] ) && 'right' == $item['align'] ) {
            $item['align'] = 'td-align-right';
        }

        if ( isset( $item['align'] ) && 'center' == $item['align'] ) {
            $item['align'] = 'td-align-center';
        }

        // or for default case
        if ( !isset( $item['align'] ) || 'left' == $item['align'] ) {
            $item['align'] = 'td-align-left';
        }

        if ( !isset( $item['filterable'] ) ) {
            $item['filterable'] = true;
        }

        if ( !isset( $item['header'] ) ) {
            if ( isset( $item['title'] ) ) {
                $item['header'] = $item['title'];
            } else {
                $item['header'] = $name;
            }
        }

        if ( !isset( $item['hidden'] ) ) {
            $item['hidden'] = false;
        }

        if ( !isset( $item['id'] ) ) {
            $item['id'] = str_replace( '.', '-', $name );
        }

        if ( !isset( $item['searchable'] ) ) {
            $item['searchable'] = 'true';
        }

        if ( !isset( $item['sortable'] ) ) {
            $item['sortable'] = true;
        }

        if ( $item['sortable'] ) {
            $item['sortIcon'] = 'icon-sort';
        } else {
            $item['sortIcon'] = '';
        }

        if ( !isset( $item['title'] ) ) {
            if ( isset( $item['header'] ) ) {
                $item['title'] = $item['header'];
            } else {
                $item['title'] = $name;
            }
        }

        if ( !isset( $item['type'] ) ) {
            $item['type'] = 'string';
        }

        if ( 'boolean' == $item['type'] ) {
            $item['html'] = true;
        }

        $this->grid['headers'][$item['column']] = $item;
    }

    public function orderColumns( $columns ) {
        $this->grid['headers'] = QueryHelper::orderColumns( $this->grid['headers'], $columns );
    }

    public function hideColumns( $columns ) {
        $this->grid['headers'] = QueryHelper::hideColumns( $this->grid['headers'], $columns );
    }

    public function getJsonResponse( $distinct = true ) {
        $orderBys = $this->queryBuilder->getDQLPart( 'orderBy' );
        $this->queryBuilder->resetDQLPart( 'orderBy' );
        $this->queryBuilder->select( $this->queryBuilder->expr()->count( 'distinct ' . $this->root . '.id' ) );
        $this->grid['total'] = $this->queryBuilder->getQuery()->getSingleScalarResult();

        $search = $this->prepend.$this->grid['search'];
        if ( isset($this->grid['negative']) && $this->grid['negative']) {
            QueryHelper::search( $this->queryBuilder, $search, $this->grid['headers'], true );
        } else {
            QueryHelper::search( $this->queryBuilder, $search, $this->grid['headers'] );
        }
        $this->grid['filtered'] = $this->queryBuilder->getQuery()->getSingleScalarResult();
        $this->queryBuilder->select( $this->root );
        foreach ( $orderBys as $k => $part ) { $this->queryBuilder->add( 'orderBy', $part ); }
        $this->removeHidden();

        $this->grid['perPageList'] = $this->perPageList;

        // for weighting
        $maxWidth = 0;
        foreach ( $this->grid['headers'] as &$header ) {
            if ( !isset( $header['width'] ) ) {
                $header['width']=1;
            }
            $maxWidth+=$header['width'];
        }

        if ( 0 < ( count( $this->grid['paths'] ) + count( $this->grid['buttons'] ) ) ) {
            $buttons=0;
            if ( isset( $this->grid['paths'] ) ) {
                $numPaths=round( count( $this->grid['paths'] ) );
                $buttons += $numPaths;
                foreach ( $this->grid['paths'] as $k =>$p ) { if ( isset( $p['text'] ) ) { $buttons++; } }
            }

            if ( isset( $this->grid['buttons'] ) ) {
                $numButtons=count( $this->grid['buttons'] );
                $buttons += $numButtons;
                foreach ( $this->grid['buttons'] as $k =>$b ) { if ( isset( $b['text'] ) ) { $buttons++; } }
            }

            $this->grid['numButtons']=$buttons;
            $buttonsWidth=floor( $buttons/3 )+1;
            $maxWidth+=$buttonsWidth;
        }

        // integral percentage
        foreach ( $this->grid['headers'] as &$header ) {
            $header['width']=floor( $header['width']/$maxWidth*100 );
        }

        if ( isset( $this->grid['numButtons'] ) ) {
            $this->grid['actionWidth']=floor( $buttonsWidth/$maxWidth*100 );
            // hardcoded values-- needs to be configured
            $this->grid['numButtons']*=30;
            $this->grid['numButtons']+=8;

        } else {
            $this->grid['actionWidth']=0;
        }

        if ( 0 < count( $this->queryBuilder->getDqlPart( 'join' ) ) ) {
            $qb=$this->queryBuilder;
            array_map(
                function( $element ) use ( $qb ) {
                    $qb->addSelect( $element->getAlias() );
                },
                $this->queryBuilder->getDqlPart( 'join' )[$this->root]
            )
            ;

        }

        if ( 0 < $this->grid['filtered'] ) {
            if ( is_null( $this->grid['page'] ) ) {
                $this->grid['page'] = 1;
            }
            if ( is_null( $this->grid['perPage'] ) ) {
                $this->grid['perPage'] = 10;
            }

            if ( $this->export ) {
                $this->grid['perPage'] = $this->grid['filtered'];
            }

            if ( !$this->export ) {
                $this->calculatePages();
            }

            $this->addSorts();

            $this->results = $this->paginator->paginate(
                $this->queryBuilder->getQuery()->setHint( 'knp_paginator.count', $this->grid['filtered'] ),
                $this->grid['page'],
                $this->grid['perPage'],
                array( 'distinct' => $distinct ) );
            $rootId = null;

            $this->processResults();

        }

        if ( $this->export ) {
            if ( $this->grid['exportType'] == 'pdf' && !is_null( $this->snappy ) ) {
                $html = $this->controller->renderView( 'MESDAngGridBundle:Grid:export.pdf.twig',
                    array(
                        'entities' => $this->grid['entities'],
                        'headers' => $this->grid['headers'],
                    )
                );
                $response = new Response(
                    $this->snappy->getOutputFromHtml( $html, array( 'orientation' => 'Landscape',
                            'print-media-type' => true,
                            'footer-left'  => 'Exported on [date] at [time]',
                            'footer-right' => 'Page [page] of [toPage]' )
                    ),
                    200,
                    array(
                        'Content-Type'          => 'application/pdf',
                        'Content-Disposition'   => 'attachment; filename="export.pdf"'
                    )
                );
                $response->headers->set( 'Content-Type', 'application/pdf' );
                $response->headers->set( 'Content-Disposition', 'attachment; filename="export.' . $this->grid['exportType'] . '"' );
                $response->setStatusCode( 200 );
                $response->headers->set( 'Content-Description', 'Export' );
                $response->headers->set( 'Content-Transfer-Encoding', 'binary' );
                $response->headers->set( 'Pragma', 'no-cache' );
                $response->headers->set( 'Expires', '0' );
            }
            else {
                $response = new Response( $this->controller->renderView( 'MESDAngGridBundle:Grid:export.' . $this->grid['exportType'] . '.twig',
                        array(
                            'entities' => $this->grid['entities'],
                            'headers' => $this->grid['headers'],
                        )
                    )
                );
                $response->headers->set( 'Content-Type', 'text/' . $this->grid['exportType'] );
                $response->headers->set( 'Content-Disposition', 'attachment; filename="export.' . $this->grid['exportType'] . '"' );
                $response->setStatusCode( 200 );
                $response->headers->set( 'Content-Description', 'Export' );
                $response->headers->set( 'Content-Transfer-Encoding', 'binary' );
                $response->headers->set( 'Pragma', 'no-cache' );
                $response->headers->set( 'Expires', '0' );
            }

            return $response;
        }

        if ( is_null( $this->grid['exportType'] ) ) {
            $this->grid['exportLink'] = '';
            foreach ( $this->grid['exportArray'] as $exType ) {
                $exType['exportLink'] = '';
            }
        } else {
            if ( !empty( $this->exportParams ) ) {
                $this->grid['exportLink'] = $this->controller->generateUrl( $this->exportAlias, array_merge( array( 'exportType' => $this->grid['exportType'] ), $this->exportParams ) ) .
                    '?exportString=true&search=' . $this->grid['search'] .
                    '&sorts=' . json_encode( $this->grid['sorts'] );
            }
            else {
                $this->grid['exportLink'] = $this->controller->generateUrl( $this->exportAlias, array( 'exportType' => $this->grid['exportType'] ) ) .
                    '?exportString=true&search=' . $this->grid['search'] .
                    '&sorts=' . json_encode( $this->grid['sorts'] );
            }
            for ( $i = 0; $i < count( $this->grid['exportArray'] ); $i++ ) {
                if ( !empty( $this->exportParams ) ) {
                    $this->grid['exportArray'][$i]['exportLink'] = $this->controller->generateUrl( $this->exportAlias,
                        array_merge( array( 'exportType' => $this->grid['exportArray'][$i]['value'] ), $this->exportParams ) ) .
                        '?exportString=true&search=' . $this->grid['search'] .
                        '&sorts=' . json_encode( $this->grid['sorts'] );
                }
                else {
                    $this->grid['exportArray'][$i]['exportLink'] = $this->controller->generateUrl( $this->exportAlias,
                        array( 'exportType' => $this->grid['exportArray'][$i]['value'] ) ) .
                        '?exportString=true&search=' . $this->grid['search'] .
                        '&sorts=' . json_encode( $this->grid['sorts'] );
                }
            }
        }

        if ( isset( $this->debug ) ) {
            return $this->controller->render( 'MESDAngGridBundle:Grid:debug.html.twig', array( 'grid' => $this->grid ) );
        }

        if ( 'js' == $this->grid['exportType'] ) {
            $response = new JsonResponse( $this->grid );

            $initData = <<<EOT
//provider style, full blown, configurable version
gridModule.provider('initData', function() {
    // In the provider function, you cannot inject any
    // service or factory. This can only be done at the
    // "\$get" method.

    this.name = 'Default';

    this.\$get = function() {
        var name = this.name;
        return {
            initData: function() {
EOT;
            $initData .= 'return ' . $response->getContent();
            $initData .= <<<EOT
;
            }
        }
    };

    this.setName = function(name) {
        this.name = name;
    };
});
EOT;

            return new Response( $initData );
        } else {
            return new JsonResponse( $this->grid );
        }
    }

    public function setFormUrl( $url ) {
        $this->grid['formUrl'] = $url;
    }

    public function prependSearch( $search ) {
        $this->prepend = $search.' ';
    }

    public function calculatePages() {
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
        $this->queryBuilder->setFirstResult( $this->grid['perPage'] * ( $this->grid['page'] - 1 ) )
        ->setMaxResults( $this->grid['perPage'] );
    }

    public function addSorts() {
        if ( isset( $this->grid['sorts'] ) && '[]' != $this->grid['sorts'] ) {
            foreach ( $this->grid['sorts'] as $sort ) {
                if ( isset( $this->grid['headers'][$sort->column] ) ) {
                    if ( isset( $this->grid['headers'][$sort->column]['column'] ) ) {
                        $this->queryBuilder->addOrderBy( $this->grid['headers'][$sort->column]['column'], $sort->direction );
                    }
                    if ( isset( $this->grid['headers'][$sort->column]['addSort'] )
                        && 'array' == gettype( $this->grid['headers'][$sort->column]['addSort'] )
                    ) {
                        foreach ( $this->grid['headers'][$sort->column]['addSort'] as $newSort ) {
                            $this->queryBuilder->addOrderBy( $newSort, $sort->direction );
                        }
                    }
                    if ( 'asc' == $sort->direction ) {
                        $this->grid['headers'][$sort->column]['sortIcon'] = 'icon-sort-up';
                    } else {
                        $this->grid['headers'][$sort->column]['sortIcon'] = 'icon-sort-down';
                    }
                }
            }
        }
    }

    public function isExport() {
        return $this->export;
    }

    public function processResults() {
        $this->resultSet = null;
        foreach ( $this->results as $result ) {
            if ( isset( $result ) ) {
                $class = get_class( $result );
                if ( $class == $this->rootClass ) {
                    if ( isset( $this->resultSet ) ) {
                        $this->processResultSet();
                    }
                    $this->resultSet = array( 'root' => $result );
                } else {
                    $this->resultSet[$class][] = $result;
                }
            }
        }
        $this->processResultSet();
    }

    public function processResultSet() {

        $paths = $this->processActions( 'paths' );
        $buttons = $this->processActions( 'buttons' );
        $values = $this->processValues();
        $labels = $this->processLabels();
        $titles = $this->processTitles( $this->processValues() );
        $this->grid['entities']['id_' . $this->resultSet['root']->getId()] = array(
            'id'      => $this->resultSet['root']->getId(),
            'paths'   => $paths,
            'buttons' => $buttons,
            'values'  => $values,
            'labels'  => $labels,
            'titles'  => $titles
        );
    }

    public function processActions( $name ) {

        //
        // For buttons batch us just the submission value
        //

        $actions = array();
        foreach ( $this->grid[$name] as $action ) {
            if ( isset( $action['function'] ) ) {
                $function = $action['function'];
                $path = $function( $this->resultSet, $this->controller );
                if ( isset( $path['path'] ) ) {
                    $actions[$action['alias']] = $path['path'];
                }
            } else {
                $actions[$action['alias']] = $this->controller->generateUrl( $action['alias'], array( 'id' => $this->resultSet['root']->getId() ) );
            }
        }
        return $actions;
    }


    public function processLabels() {
        // These are in the html5 title popups
        $labels = array();

        foreach ( array( 'paths', 'buttons' ) as $kind ) {
            foreach ( $this->grid[$kind] as $action ) {
                if ( isset( $action['title_function'] ) ) {
                    $function = $action['title_function'];
                    $label = $function( $this->resultSet, $this->controller );
                    $labels[$action['alias']] = $label['text'];
                } elseif ( isset( $action['text'] ) ) {
                    $labels[$action['alias']] = $action['text'];
                } else {
                    $labels[$action['alias']] = '';
                }
            }
        }

        return $labels;
    }

    public function processValues() {
        $values = array();

        foreach ( $this->grid['headers'] as $header ) {
            if ( isset( $header['function'] ) ) {
                $function = $header['function'];
                $value = $function( $this->resultSet, $this->controller );
                $values[$header['column']] = $value['value'];
            } else {
                if ( isset( $header['field'] ) ) {
                    $columns = explode( '.', $header['field'] );
                    $value = $this->resultSet['root'];
                    foreach ( $columns as $key => $column ) {
                        if ( isset( $value ) && $key > 0 ) {
                            $value = call_user_func( array( $value, 'get' . ucwords( $column ) ) );
                        }
                    }
                    if ( is_null( $value ) ) {
                        $value = '-';
                    }
                    $values[$header['column']] = $value;
                }
            }
        }
        return $values;
    }


    public function processTitles( $values ) {
        // HTML5 titles
        $titles = array();

        foreach ( $this->grid['headers'] as $header ) {
            if ( isset( $header['title_function'] ) ) {
                $function = $header['title_function'];
                $title = $function( $this->resultSet, $this->controller );
                $titles[$header['column']] = $title['value'];
            } else {
                $titles[$header['column']] = $values[$header['column']];
            }
        }
        return $titles;
    }


    public function removeHidden() {
        $columns = array();
        foreach ( $this->grid['headers'] as $headerKey => $header ) {
            if ( $header['hidden'] ) {
                $columns[] = $headerKey;
            }
        }
        $this->hideColumns( $columns );
    }

    public function setPerPageList( $perPageList ) {
        $this->perPageList = $perPageList;
    }

    public function setNegative($negative){
        $this->grid['negative'] = $negative;
    }
}
