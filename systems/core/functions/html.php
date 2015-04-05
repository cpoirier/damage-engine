<?php
//
// Copyright 2011 1889 Labs (contact: chris@courage-my-friend.org)
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// ============================================================================
//
// Damage Engine Copyright 2012-2015 Massive Damage, Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except 
// in compliance with the License. You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software distributed under the License 
// is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express 
// or implied. See the License for the specific language governing permissions and limitations under 
// the License.


  function text( $text )
  {
    if( is_object($text) || is_array($text) )
    {
      ob_start();
      print_r($text);
      $text = ob_get_clean();
    }
    elseif( $text == "0" )
    {
      return "&#48";   // PHP likes to print 0 as blank. Grrr.
    }

    return htmlspecialchars($text);
  }

  function nbtext( $text )
  {
    return tag("span.nowrap", text($text));
  }

  function tags()
  {
    $args = func_get_args();
    $args = array_flatten($args);
    return implode(" ", $args);
  }

  function script( $url )
  {
    return tag("script", "", array("src" => $url));
  }

  function js( $text )
  {
    return tag("script", "//<![CDATA[\n$text\n//]]>");
  }

  function css( $text )
  {
    return tag("style", "/*<![CDATA[*/\n$text\n/*//]]>*/");
  }

  function section()
  {
    $args = func_get_args();
    $name = array_shift($args);
    return tag("section#$name", array(aname("", $name), $args));
  }

  function aname( $label, $name = "", $markers = "", $if = true )
  {
    return tag($if ? "a$markers" : "a", text($label), array("name" => $name, "title" => $label));
  }

  function ahref( $label, $url = null, $markers = "", $if = true, $title = null )
  {
    return tag($if ? "a$markers" : "a", $label, array("href" => is_null($url) ? $label : $url, "title" => empty($title) ? $label : $title), false);
  }

  function img( $url, $title, $markers = "" )
  {
    $attrs = array();
    $attrs["src"  ] = $url;
    $attrs["alt"  ] = $title;
    $attrs["title"] = $title;

    return tag("img$markers", null, $attrs);
  }

  function html( $head, $body, $lang = "en", $dir = "ltr" )
  {
    ob_start();
    print "<!DOCTYPE html>\n";
    print tag("html", tags($head, $body), array("dir" => $dir, "lang" => $lang));
    return ob_get_clean();
  }

  function comment( $body )
  {
    return sprintf("<!-- %s -->", $body);
  }

  function meta_charset( $charset = "utf-8" )
  {
    return tag("meta", null, array("charset" => $charset));
  }

  function meta( $name, $content, $uppercase = false )
  {
    $name    = text($name);
    $content = text($content);

    return $uppercase ? "<META NAME=\"$name\" CONTENT=\"$content\">" : "<meta name=\"$name\" content=\"$content\">";
  }

  function robots( $no_follow = false, $no_index = false )
  {
    $index  = $no_index  ? "NOINDEX"  : "INDEX";
    $follow = $no_follow ? "NOFOLLOW" : "FOLLOW";

    return meta( "ROBOTS", "$index,$follow", true );
  }

  function title( $title )
  {
    return tag("title", text($title));
  }

  function html_link( $href, $rel = "stylesheet", $type = "text/css" )
  {
    return tag("link", "", array("rel" => $rel, "type" => $type, "href" => $href));
  }

  function code( $code )
  {
    $code = text($code);
    return tag("code", $code);
  }

  function code_block( $code, $strip_leading_whitespace = false, $markers = "" )
  {
    $code = text($code);
    if( $strip_leading_whitespace )
    {
      $m = array();
      if( preg_match("/^(\s+)/", $code, $m) )
      {
        $leading = $m[1];
        $code = preg_replace("/(*ANYCRLF)$leading/m", "\n", $code);
      }
    }

    return tag("code.block$markers", array("", $code));
  }


  function form()
  {
    $args   = func_get_args();
    $method = "POST";
    $action = "";

    if( count($args) > 2 )
    {
      if( is_null($args[0]) )
      {
        array_shift($args);
      }
      elseif( preg_match("/^[A-Z]+$/", $args[0]) )
      {
        $method = array_shift($args);
      }
    }

    if( count($args) > 2 )
    {
      if( is_null($args[0]) )
      {
        array_shift($args);
      }
      elseif( count($args) > 2 && preg_match("/[?\/]/", $args[0]) )
      {
        $action = array_shift($args);
      }
    }

    $name = array_shift($args);
    $body = $args;

    $attrs = array("name" => $name, "id" => $name, "action" => $action, "method" => strtoupper($method));
    return tag("form", $body, $attrs);
  }

  function upload_form()
  {
    $args   = func_get_args();
    $action = preg_match("/[?\/]/", $args[0]) ? array_shift($args) : "";
    $name   = array_shift($args);
    $attrs  = array("name" => $name, "id" => $name, "action" => $action, "method" => "POST", "enctype" => "multipart/form-data" );
    $body   = tags(input("MAX_FILE_SIZE", ini_get("upload_max_filesize"), "hidden"), $args);

    return tag("form", $body, $attrs);
  }

  function input( $name, $value = "", $type = "text", $disabled = false, $attrs = array() )
  {
    if( $type == "textarea" )
    {
      return textarea($name, $value, $disabled, $attrs);
    }
    else
    {
      $attrs = array_merge($attrs, array("type" => $type, "name" => $name, "id" => $name, "value" => $value));
      if( $disabled )
      {
        $attrs["disabled"] = "disabled";
      }

      return tag("input", "", $attrs);
    }
  }

  function checkbox( $name, $value, $checked, $disabled = false, $radio = false )
  {
    return input($name, $value, $radio ? "radio" : "checkbox", $disabled, $checked ? array("checked" => "checked") : array());
  }

  function radio( $name, $value, $checked, $disabled = false )
  {
    return checkbox($name, $value, $checked, $disabled, true);
  }

  function label( $name, $label )
  {
    $attrs = array("for" => $name);
    return tag("label", text($label), $attrs);
  }

  function submit( $label, $name = "" )
  {
    return input($name, $label, "submit");
  }

  function button( $label, $on_click = "", $name = "" )
  {
    return input($name, $label, "button", false, array("onclick" => $on_click));
  }

  function option( $label, $value = null, $selected = false )
  {
    $attrs = array();
    if( !is_null($value) ) { $attrs["value"   ] = $value;     }
    if( $selected        ) { $attrs["selected"] = "selected"; }
    return tag("option", text($label), $attrs);
  }

  function options( $map, $selected, $default = null )
  {
    $options = array();

    if( $default )
    {
      $options[] = $default;
    }

    foreach( $map as $value => $label )
    {
      $options[] = option($label, $value, "$value" == "$selected");
    }

    return $options;
  }

  function html_select( $name, $options, $key_is_value = false, $value = null )
  {
    $attrs = array("name" => $name);
    if( is_array($options) )
    {
      $cleaned = array();
      foreach( $options as $k => $v )
      {
        if( substr($v, 0, 7) == "<option" )
        {
          $cleaned[] = $v;
        }
        elseif( $key_is_value )
        {
          $cleaned[] = option($v, $k, $value == $k);
        }
        else
        {
          $cleaned[] = option($v, null, $value == $v);
        }
      }
      return tag("select", tags($cleaned), $attrs);
    }
    else
    {
      return tag("select", $options, $attrs);
    }
  }

  function textarea( $name, $value, $disabled = false, $attrs = array() )
  {
    $attrs = array_merge($attrs, array("name" => $name, "id" => $name));
    if( $disabled )
    {
      $attrs["disabled"] = "disabled";
    }

    return tag("textarea", $value, $attrs);
  }

  function br() { return tag("br"); }
  function hr() { return tag("hr"); }

  function head()    { $body = func_get_args(); return tag("head"   , $body); }
  function body()    { $body = func_get_args(); return tag("body"   , $body); }
  function i()       { $body = func_get_args(); return tag("i"      , $body); }
  function b()       { $body = func_get_args(); return tag("b"      , $body); }
  function p()       { $body = func_get_args(); return tag("p"      , $body); }
  function h1()      { $body = func_get_args(); return tag("h1"     , $body); }
  function h2()      { $body = func_get_args(); return tag("h2"     , $body); }
  function h3()      { $body = func_get_args(); return tag("h3"     , $body); }
  function h4()      { $body = func_get_args(); return tag("h4"     , $body); }
  function h5()      { $body = func_get_args(); return tag("h5"     , $body); }
  function h6()      { $body = func_get_args(); return tag("h6"     , $body); }
  function td()      { $body = func_get_args(); return tag("td"     , $body); }
  function th()      { $body = func_get_args(); return tag("th"     , $body); }
  function tr()      { $body = func_get_args(); return tag("tr"     , $body, null, true, true);       }
  function li()      { $body = func_get_args(); return tag("li"     , $body); }
  function ul()      { $body = func_get_args(); return tag("ul"     , $body); }
  function ol()      { $body = func_get_args(); return tag("ol"     , $body); }
  function dt()      { $body = func_get_args(); return tag("dt"     , $body); }
  function dd()      { $body = func_get_args(); return tag("dd"     , $body); }
  function pre()     { $body = func_get_args(); return tag("pre"    , $body); }
  function div()     { $body = func_get_args(); return tag("div"    , $body); }
  function span()    { $body = func_get_args(); return tag("span"   , $body, null, false); }
  function nav()     { $body = func_get_args(); return tag("nav"    , $body, null, false); }
  function article() { $body = func_get_args(); return tag("article", $body, null, false); }

  function tdcs()
  {
    $args = func_get_args();
    $cs   = 0 + array_shift($args);
    return tag("td", $args, array("colspan" => $cs));
  }

  function thcs()
  {
    $args = func_get_args();
    $cs   = 0 + array_shift($args);
    return tag("th", $args, array("colspan" => $cs));
  }

  function table()
  {
    $args = func_get_args();
    $args = array_flatten($args);
    $body = array();
    while( !empty($args) && strlen($args[0]) > 1 && substr($args[0], 0, 1) != "<" )
    {
      $body[] = array_shift($args);
    }
    $body[] = tag("tbody", $args);

    return tag("table", $body, null, true, true, true);
  }

  function success( $message ) { return tag("p.success", text($message)); }
  function notice(  $message ) { return tag("p.notice" , text($message)); }
  function warning( $message ) { return tag("p.warning", text($message)); }
  function error(   $message ) { return tag("p.error"  , text($message)); }

  function stars( $on, $total, $on_colour = "orange", $off_colour = "grey" )
  {
    if( $on > $total )
    {
      $on = $total;
    }

    return
      span(".$on_colour" , str_repeat("&#9733;", floor($on)         )) .
      span(".$off_colour", str_repeat("&#9733;", $total - floor($on)));
  }


  //
  // Builds a tag from contents. For the sake of convenience, the tag name can include
  // additional data:
  //  tag[#id]?[.class]* [attributes]
  //
  // You can additionally pass the [#id]?[.class]* [attributes] string as the first
  // string in the $elements array, but it must start with a . or a #.

  function tag( $tag, $elements = "", $attributes = null, $nlc = true, $nlo = false, $nle = false )
  {
    $attrs = array();
    $elements = is_array($elements) ? array_flatten($elements) : array($elements);

    //
    // Convert the explicit attributes into an attribute string.

    if( is_array($attributes) )
    {
      foreach( $attributes as $key => $value )
      {
        $value = text($value);
        $attrs[] = "$key=\"$value\"";
      }
    }

    //
    // Parse the tag into a tag and any included attribute string.

    if( !empty($elements) && (substr($elements[0], 0, 1) == "." || substr($elements[0], 0, 1) == "#") )
    {
      if( !is_numeric(strpos($elements[0], " ")) && !is_numeric(strpos($elements[0], "\n")) )
      {
        $tag .= array_shift($elements);
      }
    }

    @list($tag, $rest) = explode(" ", $tag, 2);
    if( strlen($rest) > 0 )
    {
      $attrs[] = $rest;
    }

    //
    // Convert any #id on the tag to an id attribute.

    if( is_numeric(strpos($tag, "#")) )
    {
      @list($tag, $rest) = explode("#", $tag);
      @list($id , $rest) = explode(".", $rest, 2);

      if( strlen($rest) > 0 )
      {
        $tag = $tag . "." . $rest;
      }

      $attrs[] = "id=\"$id\"";
    }

    //
    // Convert any .class markers on the tag to a class attribute.

    if( is_numeric(strpos($tag, ".")) )
    {
      $pieces  = explode(".", $tag);
      $tag     = array_shift($pieces);
      $classes = implode(" ", $pieces);
      if( strlen(trim($classes)) > 0 )
      {
        $attrs[] = "class=\"$classes\"";
      }
    }

    //
    // Format the tag and return it.

    ob_start();
    print "<";
    print $tag;
    if( count($attrs) > 0 )
    {
      print " ";
      print implode(" ", $attrs);
    }

    $elements = array_filter($elements);
    if( empty($elements) && $tag != "script" && $tag != "div" && $tag != "span" && $tag != "a" && $tag != "textarea" && $tag != "title" )
    {
      print ">";
    }
    else
    {
      print ">";
      if( $nlo ) { print "\n"; }
      foreach( $elements as $element )
      {
        print $element;
        if( $nle ) { print "\n"; }
      }
      print "</$tag>";
    }

    if( $nlc ) { print "\n"; }
    return ob_get_clean();
  }


  function links( $list, $current = "", $criteria = null, $urler = null )
  {
    if( is_null($criteria) && is_numeric(strpos($current, "?")) )
    {
      @list($url, $fragment) = explode("#", $current, 2);
      @list($current, $query_string) = explode("?", array_shift($fragment), 2);
      parse_str($query_string, $criteria);
    }

    $links = array();
    foreach( $list as $name => $body )
    {
      if( is_array($body) )
      {
        if( count($body) == 2 && is_string($body[0]) && is_array($body[1]) )
        {
          $links[ahref(text($name), $body[0])] = links($body[1], $current, $criteria);
        }
        else
        {
          $links[aname($name)] = links($body, $current, $criteria);
        }
      }
      else
      {
        @list($url, $query_string) = explode("?", array_shift(explode("#", $body, 2)), 2);
        $urler and $url = $urler($url);

        $is_current = ($current == $url);
        if( $is_current && !empty($criteria) && !empty($query_string) )
        {
          $pairs = array();
          parse_str($query_string, $pairs);
          $is_current = array_intersect_assoc($criteria, $pairs);
        }

        $links[] = ahref(text($name), $body, ".current", $is_current);
      }
    }

    return $links;
  }


  //
  // Converts a tree of arrays into a <ul> tree.

  function menu( $structure )
  {
    $count = count($structure);

    ob_start();
    print "<ul>\n";

    $current = 0;
    foreach( $structure as $name => $body )
    {
      $classes = array();
      if( $current == 0 ) { $classes[] = "first"; }
      if( $current == $count - 1 ) { $classes[] = "last"; }
      $classes = implode(" ", $classes);

      print "<li class=\"$classes\">";

      if( is_array($body) )
      {
        print $name;
        print menu($body);
      }
      else
      {
        print $body;
      }

      print "</li>";

      $current++;
    }
    print "</ul>\n";
    return ob_get_clean();
  }


  function pager( $links, $markers = "", $label = null )
  {
    return tag("nav$markers.pager", tags($label ? span(".label", $label) : "", implode(" • ", $links)));
  }
