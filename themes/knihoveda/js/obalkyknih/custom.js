function obalky_display_knav(element, bibinfo) {
	var href = bibinfo["cover_medium_url"];
	var backlink = bibinfo["backlink_url"];
	if (href == undefined) {
		href = bibinfo["toc_thumbnail_url"];
		backlink = bibinfo["toc_pdf_url"];
	}
	if (href != undefined) {
		var img_empty = '#obalka_empty';
		var div_cover = '#obalka_cover';
		$(img_empty).remove();
		$(div_cover).append("<a href='" + backlink + "'><img align='left' src='" + href + "' width='130'></img></a>");
	}

	toc_url = bibinfo["toc_pdf_url"];
	if (toc_url != undefined) {
		var toc = '#obalka_toc';
		toc_thumbnail_url = bibinfo["toc_thumbnail_url"];
		$(toc).append("<a href='" + toc_url + "'><img align='left' src='" + toc_thumbnail_url + "' alt='Obsah' width='130' class='toc-pdf'></img></a>");
	}
}

function obalky_display_toc(element, bibinfo) {
	if(bibinfo["toc_thumbnail_url"]) {
		var ahref = document.createElement("A");
		ahref.href = bibinfo["toc_pdf_url"];
		ahref.border = 0;
		var img = document.createElement("IMG");
		img.src = bibinfo["toc_thumbnail_url"];
		ahref.appendChild(img);
		img.style.borderStyle = "none";
		element.appendChild(ahref);
	}
}


function obalky_display_default(element, book) {
	obalky_display_cover(element, book);
	// obalky_display_rating(element, book);
}
function obalky_display_debug(element, bibinfo) { 
	alert("obalky_display_debug("+obalky.stringify(bibinfo)+")"); 
}

