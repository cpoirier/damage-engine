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


function Caller( form, tickTarget, responseArea, textTarget, htmlTarget, menuTarget )
{
  this.form         = $(form);
  this.ticker       = new Ticker(tickTarget);
  this.formatter    = null;
  this.responseArea = $(responseArea);
  this.textTarget   = $(textTarget);
  this.htmlTarget   = $(htmlTarget);
  this.menuTarget   = $(menuTarget);
  
  this.textTarget.val("");
  this.htmlTarget.empty();
  this.menuTarget.empty();
  this.responseArea.hide();
}

Caller.prototype.call = function()
{
  if( this.formatter )
  {
    this.formatter.stop();
    this.formatter = null;
  }
  
  this.ticker.start();
  this.textTarget.removeClass("major").val("");
  this.htmlTarget.removeClass("major").empty();
  this.menuTarget.empty();
  
  this.responseArea.hide();
  
  $.ajax
  ({
    url:      this.form.attr("action"),
    type:     this.form.attr("method"),
    context:  this,
    data:     this.form.serializeArray(),
    dataType: "text",
    success:  this.onSuccess,
    error:    this.onError
  });
}

Caller.prototype.onSuccess = function( data, status, xhr )
{
  this.responseArea.show();
  this.textTarget.val(data);

  try
  {
    var parsed;
    if( parsed = JSON.parse(data) )
    {
      this.ticker.transition("formatting", true);
      this.htmlTarget.addClass("major");      

      var ui;
      if( ui = parsed.ui )
      {
        var menu;
        if( menu = ui.menu )
        {
          for( var i in menu )
          {
            var li = $("<li/>").appendTo(this.menuTarget);
            var a = $("<a/>").appendTo(li);
      
            link = menu[i];
            a.attr("href", link.href);
            a.append(link.title);
          }
        }
      
        var path;
        if( path = ui.path )
        {
          this.textTarget.hide();
          var current = parsed;
          for( var i in path )
          {
            var el  = path[i];
            current = current[el];
          }
        
          if( current )
          {
            parsed = current;
          }
        }
      
        delete parsed.ui;
      }
      
      this.formatter = new Formatter(parsed, this.htmlTarget, this.ticker, parsed.grids);
    }
    else
    {
      this.textTarget.addClass("major");
      this.ticker.stop();
    }
  }
  catch( e )
  {
    this.textTarget.addClass("major");
    this.ticker.stop();
  }
}

Caller.prototype.onError = function( xhr, status, error )
{
  this.responseArea.show();
  this.htmlTarget.append("<p class='error'>" + xhr.status + " " + xhr.statusText + "</p>");
  this.onSuccess(xhr.responseText, status, xhr);
}




function Formatter( object, target, ticker, grid_paths )
{
  this.queue      = [[object, target]];
  this.timeout    = null;
  this.ticker     = ticker;
  this.anchor_in  = 10;
  this.grid_paths = grid_paths;

  this.start();
}

Formatter.prototype.stop = function()
{
  if( this.timeout != null )
  {
    window.clearTimeout(this.timeout);
    this.timeout = null;
    this.queue   = null;
  }
  
  if( this.ticker != null )
  {
    this.ticker.stop();
  }
}

Formatter.prototype.start = function()
{
  this.schedule(10);
}

Formatter.prototype.schedule = function( millis )
{
  if( this.ticker != null )
  {
    this.ticker.tick();
  }

  if( this.timeout == null )
  {
    this.timeout = window.setTimeout(function(formatter) { formatter.timeout = null; formatter.process(); }, millis, this);
  }
}


Formatter.prototype.process = function()
{
  var defer = false;
  while( !defer && this.queue != null && this.queue.length > 0 )
  {
    var frame  = this.queue.pop();
    var object = frame[0];
    var into   = frame[1];

    if( object == null )
    {
      into.append("<i>null</i>");
    }
    else if( object instanceof Array && object.length == 0 )
    {
      into.append("<i>array()</i>");
    }
    else if( object instanceof Array || object instanceof Object )
    {
      var details = null;
      if( details = this.isGridable(object, 18, true) )
      {
        var headings = details[0];
        var numbers  = details[1];
        var table    = $("<table class='grid'/>").appendTo(into);
        var thead    = $("<thead/>").appendTo(table);
        var trh      = $("<tr><th></th></tr>"   ).appendTo(thead);
        for( var i in headings )
        {
          var th = $("<th/>").appendTo(trh);
          th.append(headings[i]);
        }
        
        var tbody = $("<tbody/>").appendTo(table);
        for( var i in object )
        {
          var el = object[i];
          var tr = $("<tr/>").appendTo(tbody);
          var ti = $("<th/>").appendTo(tr);
          ti.append(i);
          
          for( var j in el )
          {
            if( j == "menu" )
            {
              var td = $("<td class='menu'/>").appendTo(tr);

              for( k in el[j] )
              {
                href = el[j][k];
                if( href.length > 0 )
                {
                  var a = $("<a/>").appendTo(td);
                  a.attr("href", href);
                  a.append(k);
                }
                else
                {
                  var s = $("<span class='disabled'/>").appendTo(td);
                  s.append(k);
                }
                td.append(" ");
              }
            }
            else
            {
              var td = $("<td/>").appendTo(tr);
              
              if( numbers[j] )
              {
                td.addClass("numeric");
              }
              
              this.queue.push([el[j], td]);
            }
          }
        }
        
        defer = true;
      }
      else
      {
        var table = $("<table/>").appendTo(into);
        var frames = [];
        for( key in object )
        {
          var tr    = $("<tr/>").appendTo(table);
          var tdk   = $("<td/>").appendTo(tr);
          var tdv   = $("<td/>").appendTo(tr);
          var frame = [object[key], tdv];

          tdk.text(key);
          frames.unshift(frame);
          if( object[key] instanceof Array || object[key] instanceof Object )
          {
            defer = true;
          }
        }
      
        for( index in frames )
        {
          this.queue.push(frames[index]);
          this.anchor();
        }
      }
    }
    else 
    {
      if( into.hasClass("numeric") && (object.toString().trim().length == 0 || parseFloat(object) == 0) )
      {
        into = $("<span class='zero'/>").appendTo(into);
      }

      if( object.constructor === String )
      {
        if( object == "" )
        {
          into.append("<i>string()</i>");
        }
        else if( object.match(/^<\w+/) ) // HTML
        {
          into.append(object); 
        }
        else if( object.indexOf("\n") > -1 ) // Code (ish)
        {
          var leading = object.match(/^\s*/)[0];
          var trimmed = object.replace(new RegExp("^" + leading, "m"), "");
          var code    = $("<code class='block'/>").appendTo(into).text(trimmed);
        }
        else
        {
          into.append($("<div/>").text(object).html());
        }
      }
      else if( object.constructor === Boolean )
      {
        into.append(object ? "true" : "false");
      }
      else
      {
        into.append(object);
      }
    }
  }
  
  if( this.queue != null )
  {
    if( this.queue.length == 0 )
    {
      if( this.ticker != null )
      {
        this.ticker.stop();
      }
    }
    else
    {
      this.schedule(1);
    }
  }
}


Formatter.prototype.anchor = function()
{
  if( this.anchor_in > 0 )
  {
    this.anchor_in = this.anchor_in - 1;
    if( this.anchor_in == 0 )
    {
      // window.location.hash = "";
      // window.location.hash = "pretty";
    }
  }
}


Formatter.prototype.isGridable = function( subject, maximumWidth, checkNumeric )
{
  var rows     = 0;
  var template = [];
  var numbers  = new Object;
  for( var i in subject )
  {
    var element = subject[i];
    if( !(element instanceof Object) )
    {
      return false;
    }
    else if( template.length == 0 )
    {
      for( var key in element )
      {
        template.push(key);
        numbers[key] = element[key] && (element[key].toString().trim().length == 0 || !isNaN(parseFloat(element[key])) && isFinite(element[key]));
      }
      
      if( template.length <= 1 || template.length > maximumWidth )
      {
        return false;
      }
    }
    else
    {
      var count = 0;
      for( var key in element )
      {
        if( template.indexOf(key) < 0 )
        {
          return false;
        }
        
        if( numbers[key] && element[key] && element[key].toString().trim().length > 0 && !(!isNaN(parseFloat(element[key])) && isFinite(element[key])) )
        {
          numbers[key] = false;
        }
        
        count++;
      }
      
      if( count != template.length )
      {
        return false;
      }
    }
    
    rows++;
  }

  if( rows > 1 )
  {
    if( checkNumeric )
    {
      return [template, numbers];
    }
    else
    {
      return template;
    }
  }
  
  return false;
}





function Ticker( control )
{
  this.control  = $(control);
  this.original = this.control.val();
  this.mode     = "loading";
  this.interval = null;
  this.ticks    = ["      ", " .    ", " . .  ", " . . .", " . .  ", " .    "];
  this.next     = 0;
  this.last     = null;
}

Ticker.prototype.stop = function() 
{
  if( this.interval )
  {
    window.clearInterval(this.interval);
    this.interval = null;
  }
  
  this.control.removeAttr('disabled');
  this.control.val(this.original);
}

Ticker.prototype.start = function()
{
  this.stop();    
  
  this.next = 0;
  this.mode = "loading";
  this.control.attr('disabled', 'disabled');

  this.tick();
  this.interval = window.setInterval(function(ticker) { ticker.tick(); }, 1000, this);
  
}

Ticker.prototype.transition = function( mode, enabled )
{
  this.mode = mode;
  
  if( enabled )
  {
    this.control.removeAttr('disabled');
  }
  else
  {
    this.control.attr('disabled', 'disabled');
  }
  
  this.tick();
}



Ticker.prototype.tick = function()
{
  var now = new Date().getTime();
  if( this.last == null || this.last <= now - 950 )
  {
    this.control.val(this.mode + this.ticks[this.next]);
    this.next = (this.next + 1) % 6;
    this.last = now;
  }
}

