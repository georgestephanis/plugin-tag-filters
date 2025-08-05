import './list-table.scss';

const pluginTableTagFiltersList = document.querySelector(
	'.plugin-table-tag-filters'
);
const pluginsList = document.getElementById( 'the-list' );

pluginTableTagFiltersList.addEventListener( 'click', ( event ) => {
	const filterLink = event.target;

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
			const previouslyActive =
				pluginTableTagFiltersList.querySelector( '.active' );
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
} );

pluginTableTagFiltersList.addEventListener( 'keydown', ( event ) => {
	if ( event.code === 'Space' || event.code === 'Enter' ) {
		event.target.click();
	}
} );
