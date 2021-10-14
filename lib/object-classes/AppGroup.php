<?php

/**
 * ISC License
 *
 * Copyright (c) 2014-2018 Christophe Painchaud <shellescape _AT_ gmail.com>
 * Copyright (c) 2019, Palo Alto Networks Inc.
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */


class AppGroup extends App
{
    use XmlConvertible;

    protected $groupapps = array();

    /**
     * @return string
     */
    public function &getXPath()
    {
        $str = $this->owner->getAppGroupStoreXPath() . "/entry[@name='" . $this->name . "']";

        return $str;
    }

    public function load_from_domxml( $appx )
    {
        $cursor = DH::findFirstElement('members', $appx);
        if( $cursor === FALSE )
            return false;

        foreach( $cursor->childNodes as $function )
        {
            if( $function->nodeType != XML_ELEMENT_NODE )
                continue;

            $groupapp = $this->owner->find($function->textContent);

            if( $groupapp !== null )
            {
                $this->groupapps[$groupapp->name()] = $groupapp;
                $groupapp->addReference( $this );
            }
            else
            {
                $groupapp = $this->owner->findOrCreate($function->textContent);
                $this->groupapps[$groupapp->name()] = $groupapp;
                $groupapp->addReference( $this );
            }
        }

        ksort( $this->groupapps );

    }

}