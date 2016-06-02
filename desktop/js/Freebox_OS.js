$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$('.EquipementReseau').on('click', function() {
	$.ajax({
		type: 'POST',            
		async: false,
		url: 'plugins/Freebox_OS/core/ajax/Freebox_OS.ajax.php',
		data:
			{
			action: 'SearchReseau'
			},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {},
		success: function(data) {
			}
		});
});
function addCmdToTable(_cmd) {
	switch($('.eqLogicAttr[data-l1key=logicalId]').val()){
		case 'Reseau':
			$('.EquipementReseau').show();
			$('.TvParameter').hide();
		break;
		case 'FreeboxTv':
			$('.EquipementReseau').hide();
			$('.TvParameter').show();
		break;
		default:
			$('.EquipementReseau').hide();
			$('.TvParameter').hide();
		break;
	}
	if (!isset(_cmd)) {
        var _cmd = {};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
	var tr =$('<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">');
  	tr.append($('<td>')
		.append($('<div>')
			.append($('<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove">')))
		.append($('<div>')
			.append($('<i class="fa fa-arrows-v pull-left cursor bt_sortable" style="margin-top: 9px;">'))));
	tr.append($('<td>')
		.append($('<div>')
			.append($('<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">'))
			.append($('<input class="cmdAttr form-control input-sm" data-l1key="name" value="' + init(_cmd.name) + '" placeholder="{{Name}}" title="Name">'))));
	tr.append($('<td>')
			.append($('<input type="hidden" class="cmdAttr" data-l1key="template" data-l2key="dashboard" />'))
			.append($('<input type="hidden" class="cmdAttr" data-l1key="template" data-l2key="mobile" />'))
			.append($('<input type="hidden" class="cmdAttr" data-l1key="configuration" data-l2key="host_type" />'))
			.append($('<input type="hidden" class="cmdAttr" data-l1key="type" />'))
			.append($('<input type="hidden" class="cmdAttr" data-l1key="subType" />'))	
		.append($('<div style="width : 40%;display : inline-block;">')
			.append($('<span>')
				.append($('<input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-label-text="{{Historiser}}" data-l1key="isHistorized" checked/>')))
			.append($('</br>'))
			.append($('<span>')
				.append($('<input type="checkbox" class="cmdAttr bootstrapSwitch" data-size="mini" data-label-text="{{Afficher}}" data-l1key="isVisible" checked/>'))))
			.append($('<div style="width : 40%;display : inline-block;">')	
				.append($('<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="Unité"/>'))));  
		var parmetre=$('<td>');
	if (is_numeric(_cmd.id)) {
		parmetre.append($('<a class="btn btn-default btn-xs cmdAction" data-action="test">')
			.append($('<i class="fa fa-rss">')
				.text('{{Tester}}')));
	}
	parmetre.append($('<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure">')
		.append($('<i class="fa fa-cogs">')));
	tr.append(parmetre);
	$('#table_cmd tbody').append(tr);
	$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
	}
