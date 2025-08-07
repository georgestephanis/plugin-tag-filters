import './list-table.scss';

const pluginFilterForm = document.getElementById( 'plugin-filter' );

const setupFilterListeners = ( event ) => {
	const filterLink = event.target;
	const pluginsList = pluginFilterForm.querySelector( '#the-list' );

	if ( filterLink.dataset.slugs ) {
		event.preventDefault();
		if ( filterLink.classList.contains( 'active' ) ) {
			filterLink.classList.remove( 'active' );
			pluginsList.classList.remove( 'filtered' );
			pluginsList
				.querySelectorAll( '.plugin-card.filtered__show' )
				.forEach( ( item ) =>
					item.classList.remove( 'filtered__show' )
				);
		} else {
			const previouslyActive = pluginFilterForm.querySelector(
				'.plugin-table-tag-filters .active'
			);
			if ( previouslyActive ) {
				previouslyActive.classList.remove( 'active' );
				pluginsList.classList.remove( 'filtered' );
				pluginsList
					.querySelectorAll( '.plugin-card.filtered__show' )
					.forEach( ( item ) =>
						item.classList.remove( 'filtered__show' )
					);
			}

			const slugs = JSON.parse( filterLink.dataset.slugs );
			const classes = slugs.map( ( slug ) => '.plugin-card-' + slug );
			const selector = classes.reduce(
				( accumulator, currentValue ) =>
					accumulator + ', ' + currentValue
			);

			filterLink.classList.add( 'active' );
			pluginsList.classList.add( 'filtered' );
			pluginsList
				.querySelectorAll( selector )
				.forEach( ( item ) => item.classList.add( 'filtered__show' ) );
		}
	}
};

pluginFilterForm.addEventListener( 'click', setupFilterListeners );

pluginFilterForm.addEventListener( 'keydown', ( event ) => {
	if ( event.target.dataset.slugs ) {
		if ( event.code === 'Space' || event.code === 'Enter' ) {
			event.target.click();
		}
	}
} );
