<?php
/*
 * Copyright 2017 BBC
 *
 * Author: Elliot Smith <elliot.smith@bbc.co.uk>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace res\liblod;

use res\liblod\LOD;
use res\liblod\Rdf;
use res\liblod\LODResponse;

use \ArrayAccess;
use \Iterator;

/**
 * RDF model with methods for easy access to predicates and objects.
 *
 * The raw triples for the resource represented by this instance are
 * accessible via:
 *
 *   $instance->model
 */
class LODInstance implements ArrayAccess, Iterator
{
    use LODInstanceIterator;
    use LODInstanceArrayAccess;

    /**
     * The LOD context this instance comes from.
     * @property LOD $context
     */
    protected $context;

    /**
     * The subject URI of this instance.
     * @property string $uri
     */
    protected $uri;

    /**
     * Array of res\liblod\LODStatement objects
     * @property array $model
     */
    protected $model;

    // for the iterator; NB has to be protected so the FilteredLODInstance
    // can access it
    protected $position = 0;

    // res\liblod\RDF helper
    private $rdf;

    // Generated keys of statements in the model (used to prevent
    // duplicates)
    private $statementKeys = array();

    /**
     * Constructor.
     *
     * @param res\liblod\LOD $context
     * @param string $uri
     * @param array $model Array of res\liblod\LODStatement objects belonging
     * to this instance; all of these statements should have the same
     * subject URI.
     * @param res\liblod\Rdf $rdf RDF helper
     */
    public function __construct(LOD $context, $uri, $model=array(), $rdf=NULL)
    {
        if(empty($rdf))
        {
            $rdf = new Rdf();
        }

        $this->context = $context;
        $this->uri = $uri;
        $this->model = $model;
        $this->rdf = $rdf;
    }

    public function __get($name)
    {
        switch($name)
        {
            case 'uri':
                return $this->uri;
            case 'model':
                return $this->model;
            case 'exists':
                return $this->exists();
        }
        return NULL;
    }

    public function __set($name, $value)
    {
        switch($name)
        {
            case 'uri':
            case 'model':
            case 'exists':
                trigger_error("The LODInstance::$name property is read-only", E_USER_WARNING);
        }
        $this->{$name} = $value;
    }

    /**
     * Add a LODStatement, but only if the same subject+predicate+object isn't
     * already in the model.
     *
     * @param res\liblod\LODStatement $statement
     */
    public function add($statement)
    {
        $key = $statement->getKey();
        if(!in_array($key, $this->statementKeys))
        {
            $this->model[] = $statement;
            $this->statementKeys[] = $key;
        }
    }

    /**
     * Check whether the URI of this instance exists in the LOD context
     * associated with it.
     *
     * @return bool TRUE if the subject exists in the related context
     */
    public function exists()
    {
        $found = $this->context->locate($this->uri);
        return $found !== FALSE;
    }

    /**
     * Check whether this instance has one of the specified $rdfTypesToMatch.
     * NB if multiple types are passed as arguments, this returns as soon as
     * one matching type is found.
     *
     * @param ...string $rdfTypesToMatch One or more RDF URIs to check for
     *
     * @return bool TRUE if this instance has rdf:type <$rdfType>
     */
    public function hasType(...$rdfTypesToMatch)
    {
        $instanceTypes = $this->filter('rdf:type');

        foreach($rdfTypesToMatch as $rdfTypeToMatch)
        {
            $rdfTypeToMatch = $this->rdf->expandPrefix($rdfTypeToMatch);

            foreach($instanceTypes as $rdfType)
            {
                if($rdfType->value === $rdfTypeToMatch)
                {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Create a new LODInstance containing a subset of the triples
     * in this one whose predicates match the provided query.
     *
     * $query is a string of RDF predicates, which can use short-hand terms for
     * prefixes (e.g. 'rdfs:seeAlso') or full URIs
     * (e.g. 'http://schema.org/about). If using prefixes, these should be
     * set on the LOD context for this LODInstance.
     *
     * Either a single predicate or multiple (comma-separated) predicates can be
     * specified, e.g.'dcterms:title,rdfs:label'.
     *
     * When matching triples, if the triple is a literal with a 'lang'
     * specifier, the 'lang' is compared to the languages set on the LOD
     * context; only literals with matching language (or no language) will be
     * included in the output LODInstance.
     *
     * NB this is the method behind the array accessor methods.
     *
     * @param string $query Predicates to query for; see description for syntax.
     *
     * @return res\liblod\FilteredLODInstance An instance containing matching
     * triples.
     */
    public function filter($query)
    {
        $prefixes = $this->context->prefixes;

        // parse and expand the query string
        $predicates = explode(',', $query);
        foreach($predicates as $index => $predicate)
        {
            $predicates[$index] = $this->rdf->expandPrefix(trim($predicate), $prefixes);
        }

        $languages = $this->context->languages;

        // get triples which match the query
        $filterFn = function($item) use($predicates, $languages)
        {
            $predicateMatches = in_array($item->predicate->value, $predicates);

            // if the object has no 'lang' key, we can't compare languages,
            // so we just assume the language is OK
            $langOK = TRUE;
            if(($item->object instanceof LODLiteral) && ($item->object->language !== NULL))
            {
                $langOK = in_array($item->object->language, $languages);
            }

            return $predicateMatches && $langOK;
        };

        $filtered = array_filter($this->model, $filterFn);

        // sort the results depending on the language of the object literals
        // so statements in our most-preferred language are first
        $sortFn = function ($item1, $item2) use($languages)
        {
            // if an item isn't a literal, it doesn't have a language,
            // so it can't be assigned a rank
            $item1Rank = (
                $item1->object->isResource() ?
                    FALSE :
                    array_search($item1->object->language, $languages)
            );

            $item2Rank = (
                $item2->object->isResource() ?
                    FALSE :
                    array_search($item2->object->language, $languages)
            );

            $lowestRank = count($languages);

            // if the language isn't in the array or the item isn't a literal,
            // set the rank to the lowest
            $item1Rank = ($item1Rank === FALSE ? $lowestRank : $item1Rank);
            $item2Rank = ($item2Rank === FALSE ? $lowestRank : $item2Rank);

            return $item1Rank - $item2Rank;
        };

        usort($filtered, $sortFn);

        // create a new FilteredLODInstance with the filtered triples
        return new FilteredLODInstance($this->context, $this->uri, $filtered);
    }

    /**
     * Create a string representation of this instance.
     *
     * @return string The subject URI is used as the string representation
     * for an unfiltered LODInstance
     */
    public function __toString()
    {
        return $this->uri;
    }
}

/**
 * A LODInstance which has been created by filtering another LODINstance.
 * This changes how the LODInstance displays as a string, and how the iterator
 * code works.
 */
class FilteredLODInstance extends LODInstance
{
    /**
     * Gets the object of the LODStatement at the current iterator position.
     * As instance is filtered, this returns just the statement's object as
     * a LODTerm, rather than the full LODTerm.
     *
     * @return LODTerm The object of the statement at the current position
     */
    public function current()
    {
        $statement = $this->model[$this->position];
        return $statement->object;
    }

    /**
     * Create a string representation of the instance.
     *
     * @return string This will be the first value of the instance's first
     * triple, or an empty string if it has no triples
     */
    public function __toString()
    {
        if(count($this->model) > 0)
        {
            return $this->model[0]->object->value;
        }
        return '';
    }
}

// Iterator implementation
trait LODInstanceIterator
{
    /**
     * Get the statement at the current iterator position.
     *
     * @return LODStatement The full LODStatement at this position in the model
     */
    public function current()
    {
        return $this->model[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->model[$this->position]);
    }
}

// ArrayAccess implementation
trait LODInstanceArrayAccess
{
    /**
     * Check whether an offset exists in the instance; "exists" is assumed to
     * mean that the query returns a LODInstance with at least one matching
     * LODStatement in its model.
     *
     * @return bool
     */
    public function offsetExists($query)
    {
        $instance = $this->filter($query);
        return count($instance->model) > 0;
    }

    public function offsetGet($query)
    {
        return $this->filter($query);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetSet($offset, $value)
    {
        trigger_error("LODInstance array members are read-only", E_USER_NOTICE);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetUnset($offset)
    {
        trigger_error("LODInstance array members are read-only", E_USER_NOTICE);
    }
}
