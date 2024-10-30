(function(a){a.widget("lws.lac_taglist",{options:{classe:"",name:"",placeholder:"",shared:false,addlabel:"Add",delay:300,minlength:1,minoptions:2,minsearch:1,comprehensiveSource:false},_create:function(){this._setOptions();this._createStructure();this.initList=(this.element.data("value")!=undefined&&this.element.data("value").trim()!="")?lwsBase64.toObj(this.element.data("value")):undefined;this.valueList=[];this.name=this.element.prop("name");this.element.data("lw_name",this.name).prop("name","");this._manageModel();this.currentIndex=-1;this.preventNext=false;this.container.on("click",".lac-tag-remove",this._bind(this._delTag,this));this.container.on("click",".lac-taglist-addbutton",this._bind(this._addTags,this));this.container.on("focus",".lac-taglist-buffer",this._bind(this._getFocus,this));this.container.on("click",".lac-taglist-item",this._bind(this._selectItem,this));this.container.on("keydown",this._bind(this._manageKeys,this));this.element.on("change",this._bind(this._changeElement,this));var b=this;var d;var c=[];this.container.keyup(function(e,f){c[0]=e.key;c[1]=e.keyCode;sentData=[c];d&&clearTimeout(d);d=setTimeout(b._bindD(b._manageSearch,b,sentData),b.options.delay)})},_bind:function(b,c){return function(){return b.apply(c,arguments)}},_bindD:function(b,c,d){return function(){return b.apply(c,d)}},_setOptions:function(){if(this.element.data("placeholder")!=undefined){this.options.placeholder=this.element.data("placeholder")}if(this.element.data("delay")!=undefined){this.options.delay=this.element.data("delay")}if(this.element.data("class")!=undefined){this.options.classe=this.element.data("class")}if(this.element.data("shared")!=undefined){this.options.shared=this.element.data("shared")}if(this.element.data("addlabel")!=undefined){this.options.shared=this.element.data("addlabel")}if(this.element.data("comprehensive")!=undefined){this.options.comprehensiveSource=this.element.data("comprehensive")}},_createStructure:function(){this.container=this.element.parent();a("<div>",{"class":"lac-taglist-wrapper "}).append(a("<div>",{"class":"lac-taglist-top"}).append(a("<div>",{"class":"lac-taglist-combo"}).append(a("<input>",{"class":"lac-taglist-input "+this.options.classe,placeholder:this.options.placeholder,name:this.options.name})).append(a("<div>",{"class":"lac-taglist-addbutton",html:this.options.addlabel}))).append(a("<div>",{"class":"lac-taglist-list","data-open":false})).append(a("<div>",{"class":"lac-taglist-error"})).append(a("<input>",{"class":"lac-taglist-buffer"}))).append(a("<div>",{"class":"lac-taglist-bottom"}).append(a("<div>",{"class":"lac-taglist-tags"}))).append(a("<div>",{"class":"lac-taglist-values"})).appendTo(this.container);this.element.hide().detach().prependTo(this.container.find(".lac-taglist-wrapper"));this.selectList=this.container.find(".lac-taglist-list");this.tagsList=this.container.find(".lac-taglist-tags");this.textInput=this.container.find(".lac-taglist-input")},_manageModel:function(){if(this.options.shared){if(a("#sha-"+this.options.shared).length){this.model=a("#sha-"+this.options.shared)}else{this.model=a("<input>",{id:"sha-"+this.options.shared,type:"hidden"}).appendTo(a("body"))}}else{this.model=this.element}a(this.model).lac_model({resChange:{target:this,fn:this._resultChanged},resLoad:{target:this,fn:this._ajaxLoading},mode:"autocomplete",origin:this});if(this.initList!=undefined){this._ajaxLoading();a(this.model).lac_model("returnLabels",this.initList,this)}},_recursiveList:function(d){var b=[];for(var c in d){if(d[c].group!=undefined){retour=this._recursiveList(d[c].group);if(retour.length>0){if(retour[0][0].className!="lac-taglist-optgroup"){var e=a("<div>",{"class":"lac-taglist-optgroup","data-value":d[c].value,html:d[c].label});b.push(e)}b=a.merge(b,retour)}}else{this.selectIndex+=1;var e=a("<div>",{"class":"lac-taglist-item lac-item-"+this.selectIndex,"data-value":d[c].value,"data-label":d[c].label,"data-index":this.selectIndex,html:d[c].label});b.push(e)}}return b},_setResList:function(c){this.selectList.empty();this.currentIndex=-1;this.selectIndex=-1;var d=this._recursiveList(c);for(var b=0;b<d.length;b++){d[b].appendTo(this.selectList)}this.container.find(".lac-taglist-item").removeClass("lac-highlighted")},_openList:function(){this.selectList.data("open",true);this.selectList.show()},_closeList:function(){this.selectList.data("open",false);this.selectList.hide()},_changeElement:function(b){this.valueList=[];this.initList=(this.element.data("value")!=undefined&&this.element.data("value").trim()!="")?lwsBase64.toObj(this.element.data("value")):undefined;a(this.model).lac_model("returnLabels",this.initList,this)},_selectItem:function(b,c){var d=b.currentTarget.textContent;searchElements=this._getSearchElements();this.posIndex=searchElements.currentIndex;valArray=a.map(this.textInput.val().split(","),a.trim);valArray[this.posIndex]=d;curPos=searchElements.posSep[this.posIndex]+d.length;this.textInput.val(valArray.join(", "));this.textInput.val(this.textInput.val()+", ");this.textInput[0].setSelectionRange(this.textInput.val().length,this.textInput.val().length);this._closeList();this.currentIndex=a(b.currentTarget).data("index");this.textInput.focus()},_getFocus:function(b,c){if(this.preventNext){this.preventNext=false;this.textInput.focus()}},_getSearchElements:function(){var c={};c.currentPos=this.textInput[0].selectionStart;c.currentEndPos=this.textInput[0].selectionEnd;c.currentIndex=0;c.posSep=[0];posIndex=0;var d=this.textInput.val();for(var b=0;b<d.length;b++){if(d[b]===","){posIndex+=1;c.posSep[posIndex]=b}if(b+1==c.currentPos){c.currentIndex=posIndex}}return c},_manageSearch:function(b){searchElements=this._getSearchElements();this.posIndex=searchElements.currentIndex;valArray=a.map(this.textInput.val().split(","),a.trim);if(/[a-zA-Z0-9-_ ]/.test(String.fromCharCode(b[1]))){a(this.model).lac_model("research",valArray[this.posIndex],this)}},_afterArrowKeys:function(){var d=this.container.find(".lac-item-"+this.currentIndex);d.addClass("lac-highlighted");var c=this._getSearchElements();this.posIndex=c.currentIndex;var e=a.map(this.textInput.val().split(","),a.trim);e[this.posIndex]=d.data("label");var b=c.posSep[this.posIndex]+d.data("label").length+2;this.textInput.val(e.join(", "));this.textInput[0].setSelectionRange(b,b);if(this.selectList.data("open"==false)){this.selectList.data("open",true);this.selectList.show()}},_manageKeys:function(b,c){if(b.key=="ArrowDown"){if(jQuery.isEmptyObject(this.selectList)){return}this.container.find(".lac-item-"+this.currentIndex).removeClass("lac-highlighted");this.currentIndex=(this.currentIndex+1>this.selectIndex)?0:this.currentIndex+1;this._afterArrowKeys()}if(b.key=="ArrowUp"){if(jQuery.isEmptyObject(this.selectList)){return}this.posIndex=this._getSearchElements();this.container.find(".lac-item-"+this.currentIndex).removeClass("lac-highlighted");this.currentIndex=(this.currentIndex-1<0)?this.selectIndex:this.currentIndex-1;this._afterArrowKeys()}if(b.key=="Enter"){this.container.find(".lac-taglist-addbutton").trigger("click");this.selectList.data("open",false);this.selectList.hide()}if(b.key=="Tab"){if(this.selectList.data("open")==true){searchElements=this._getSearchElements();if((searchElements.currentEndPos+2)<this.textInput.val().length){this.textInput[0].setSelectionRange(searchElements.currentEndPos+2,searchElements.currentEndPos+2)}else{this.textInput.val(this.textInput.val()+", ");this.textInput[0].setSelectionRange(this.textInput.val().length,this.textInput.val().length)}this.selectList.data("open",false);this.selectList.hide();this.preventNext=true}else{this.container.find(".lac-taglist-addbutton").trigger("click")}}if(b.key=="Backspace"||b.key=="Delete"||b.key=="Suppr"||b.key==","){this.selectList.data("open",false);this.selectList.hide()}},_inputSelectRange:function(){if(this.textInput[0].setSelectionRange){valArray=a.map(this.textInput.val().split(","),a.trim);var d=this.container.find(".lac-item-"+this.currentIndex);var e="";var c=0;for(var b=0;b<valArray.length;b++){if(b==this.posIndex){start=c+valArray[b].length;end=c+d.data("label").length;e+=d.data("label")+", "}else{e+=valArray[b]+", ";c+=valArray[b].length+2}}this.textInput.focus();this.textInput.val(e.substring(0,e.length-2));this.textInput[0].setSelectionRange(start,end)}},_showError:function(b){this.container.find(".lac-taglist-error").html(b).show().delay(1000).fadeOut(500)},_ajaxLoading:function(){this.textInput.addClass("lac-loading")},_resultChanged:function(c){this.textInput.removeClass("lac-loading");if(c[1]=="ok"){if(!jQuery.isEmptyObject(c[0])){this.resList=c[0];this._setResList(this.resList);this.currentIndex=0;this._inputSelectRange();this.container.find(".lac-item-"+this.currentIndex).addClass("lac-highlighted");this._openList()}}else{if(c[1]=="init"){if(!jQuery.isEmptyObject(c[0])){var b=this;a.each(c[0],function(d,e){b.valueList[d]=e.label});this._fillTagList();this._updateList()}}else{this.resList=c[0];this._setResList(this.resList);this._showError(c[1])}}},_fillTagList:function(){this.tagsList.empty();for(var b in this.valueList){a("<div>",{"class":"lac-tag-wrapper","data-value":b}).append(a("<div>",{"class":"lac-tag-text",html:this.valueList[b]})).append(a("<a>",{"class":"lac-tag-remove lws-icon-cross"})).appendTo(this.tagsList)}if(this.valueList){a(this.model).lac_model("addToSource",this.valueList)}},_delTag:function(c){var b=a(c.currentTarget).parent();delete this.valueList[b.data("value")];b.remove();this._updateList()},_addTags:function(){labArray=a.grep(a.map(this.textInput.val().split(","),a.trim),function(c){return c.length>0});valArray=a(this.model).lac_model("getValuesFromLabels",labArray,this.options.comprehensiveSource);me=this;a.each(valArray,function(c,d){me.valueList[d.value]=d.label});var b="";if((labArray.length!=valArray.length)&&this.options.comprehensiveSource){a.each(labArray,function(c,e){var f=false;for(var d=0;(d<valArray.length)&&!f;d++){f=(e==valArray[d].label)}if(!f){if(b.length>0){b+=", "}b+=e}})}this.textInput.val(b);this._fillTagList();this._updateList();if((labArray.length!=valArray.length)&&this.options.comprehensiveSource){this._showError(lws_lac_taglist.value_unknown)}},_updateList:function(){var b=this.container.find(".lac-taglist-values");b.empty();keyTable=[];for(var c in this.valueList){a("<input>",{type:"hidden",name:this.name+"[]",value:c,"data-lw_dependant":this.name}).appendTo(b);keyTable.push(c)}this.element.data("value",lwsBase64.fromObj(keyTable))}})})(jQuery);jQuery(function(a){a(".lac_taglist").lac_taglist()});