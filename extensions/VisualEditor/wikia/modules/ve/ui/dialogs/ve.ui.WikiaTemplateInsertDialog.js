/*
 * VisualEditor user interface WikiaTemplateInsertDialog class.
 */

/**
 * Dialog for inserting templates.
 *
 * @class
 * @extends ve.ui.Dialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.WikiaTemplateInsertDialog = function VeUiWikiaTemplateInsertDialog( config ) {
	// Parent constructor
	ve.ui.WikiaTemplateInsertDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.WikiaTemplateInsertDialog, ve.ui.Dialog );

/* Static Properties */

ve.ui.WikiaTemplateInsertDialog.static.name = 'wikiaTemplateInsert';

ve.ui.WikiaTemplateInsertDialog.static.icon = 'template';

ve.ui.WikiaTemplateInsertDialog.static.title = OO.ui.deferMsg( 'visualeditor-dialog-transclusion-insert-template' );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.WikiaTemplateInsertDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.WikiaTemplateInsertDialog.super.prototype.initialize.call( this );

	// Properties
	this.stackLayout = new OO.ui.StackLayout( { '$': this.$ } );
	this.panel = new OO.ui.PanelLayout( { '$': this.$ } );
	this.select = new OO.ui.SelectWidget( { '$': this.$ } );

	// Events
	this.select.connect( this, {
		'select': 'onTemplateSelect'
	} );

	// Initialization
	this.frame.$content.addClass( 've-ui-wikiaTemplateInsertDialog' );

	this.panel.$element.append( this.select.$element );
	this.stackLayout.addItems( [ this.panel ] );

	this.$body.append( this.stackLayout.$element );

	this.getMostLinkedTemplateData().done( ve.bind( this.populateOptions, this ) );
};

/**
 * Handle selecting results.
 *
 * @method
 * @param {ve.ui.OptionWidget} item Item whose state is changing or null
 */
ve.ui.WikiaTemplateInsertDialog.prototype.onTemplateSelect = function ( item ) {
	var template;

	if ( item ) {
		this.transclusionModel = new ve.dm.MWTransclusionModel();

		template = ve.dm.MWTemplateModel.newFromName(
			this.transclusionModel, item.getData().title
		);
		this.transclusionModel.addPart( template )
			.done( ve.bind( this.insertTemplate, this ) );
	}
};

/**
 * Insert template
 */
ve.ui.WikiaTemplateInsertDialog.prototype.insertTemplate = function () {
	this.surface.getModel().getDocument().once( 'transact', ve.bind( this.onTransact, this ) );

	// Collapse returns a new fragment, so update this.fragment
	this.fragment = this.getFragment().collapseRangeToEnd();
	this.transclusionModel.insertTransclusionNode( this.getFragment() );
};

/**
 * Handle document model transaction
 */
ve.ui.WikiaTemplateInsertDialog.prototype.onTransact = function () {
	ve.ui.commandRegistry.getCommandForNode(
		this.surface.getView().getFocusedNode()
	).execute( this.surface );
};

/**
 * Use the given template data to generate option widgets and populate the dialog's select widget
 *
 * @param {array} templates
 */
ve.ui.WikiaTemplateInsertDialog.prototype.populateOptions = function ( templates ) {
	var i,
		options = [];

	for ( i = 0; i < templates.length; i++ ) {
		options.push(
			new ve.ui.WikiaTemplateOptionWidget(
				templates[i],
				{
					'$': this.$,
					'icon': 'template-inverted',
					'label': templates[i].title,
					'appears': templates[i].uses
				}
			)
		);
	}

	this.select.clearItems();
	this.select.addItems( options );
};

/**
 * Fetch the most-linked templates data
 *
 * @returns {jQuery.Promise}
 */
ve.ui.WikiaTemplateInsertDialog.prototype.getMostLinkedTemplateData = function () {
	var deferred;

	if ( !this.templatesPromise ) {
		deferred = $.Deferred();

		ve.init.target.constructor.static.apiRequest( {
			'action': 'templatesuggestions'
		} )
			.done( function ( data ) {
				deferred.resolve( data.templates );
			} )
			.fail( function () {
				deferred.resolve( [] );
			} );

		this.templatesPromise = deferred.promise();
	}

	return this.templatesPromise;
};

/**
 * @inheritdoc
 */
ve.ui.WikiaTemplateInsertDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.WikiaTemplateInsertDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			// Unselect
			this.select.selectItem();
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.WikiaTemplateInsertDialog );
