AgilePHP.IDE.Editor = function( id, type ) {

		this.editors = [];

		if( this.editors.indexOf( type + '-editor-' + id ) !== -1 )
			throw( 'Editor already exists' );

		this.editors.push( type + '-editor-' + id );

		switch( type ) {

			case 'code':
				return new Ext.Panel({

					id: 'code-editor-' + id,
					title: 'Code',
					width: AgilePHP.IDE.Desktop.getTabPanel().getInnerWidth(),
			        html: '<iframe frameborder="0" src="' + AgilePHP.getRequestBase() + '/FileExplorerController/load/code/' + id + '" ' + 
			        			'width="100%" height="100%"/>'
			    });
				break;

			case 'design':

				new AgilePHP.XHR().request( AgilePHP.getRequestBase() + '/FileExplorerController/load/design/' + id, function( response ) {

					var e = Ext.getCmp( 'design-editor-' + response.id );
						e.setValue( unescape( response.code ) );
				});

				return new Ext.form.HtmlEditor({

						id: 'design-editor-' + id,
						title: 'Design',
						width: AgilePHP.IDE.Desktop.getTabPanel().getInnerWidth(),
						height: AgilePHP.IDE.Desktop.getTabPanel().getFrameHeight(),
						listeners: {
	
							render: function( component ) {
	
								/*
								component.add({
	
								  id: 'btnTestButton',
								  text: 'test'
								});
								component.doLayout();
								*/
							}
						}
				});
				break;
		}
};