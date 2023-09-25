<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document                                                           *
*                                                                              *
*   An abstract class for documents -- html, markdown, and others.             *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2023 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius;

use RuntimeException;


/*******************************************************************************
*                                                                              *
*   \Asinius\Document                                                          *
*                                                                              *
*******************************************************************************/

abstract class Document
{
    protected $_data = null;


    /**
     * Create a new Asinius\Document object.
     *
     * @param   mixed    $data
     */
    public function __construct ($data)
    {
        if ( is_string($data) ) {
            //  TODO: If URL, make a request and then call __construct() again
            //    with the appropriate data depending on the MIME type of the response.
            //    e.g. 'text/html', 'text/plain', 'text/markdown'
            $this->_data = $data;
        }
        else if ( @is_resource($data) ) {
            $this->__construct(new Datastream\Resource($data));
        }
        else if ( is_a($data, Datastream\Resource::class) ) {
            //  Retrieve content. All content must be available before continuing;
            //  although some formats can be streamed, others may not parse
            //  properly without all the content.
            $this->_data = '';
            $stream = new Datastream\Resource($data);
            while ( ! $stream->empty() ) {
                $this->_data .= $stream->read();
            }
        }
        else if ( is_object($data) ) {
            //  Hope for the best.
            $this->_data = clone $data;
        }
        else {
            throw new RuntimeException(sprintf("Can't create a %s from this type of value: %s", __CLASS__, gettype($data)));
        }
    }

    abstract public function mime_type(): string;

    abstract public function to_html(): string;
}
