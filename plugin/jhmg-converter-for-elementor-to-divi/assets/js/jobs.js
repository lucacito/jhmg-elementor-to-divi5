/**
 * Tools → Conversion Jobs. Plain jQuery (bundled with WordPress); no build step.
 * Server-rendered HTML does the layout; this file loads fragments, runs jobs a
 * chunk at a time, and keeps the summary bar current without a page reload.
 */
( function ( $ ) {
    'use strict';

    var cfg = window.edcJobs || {};
    var t = cfg.i18n || {};

    // ---------------------------------------------------------------- helpers

    function request( action, data ) {
        return $.ajax( {
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: $.extend( { action: 'edc_jobs_' + action, nonce: cfg.nonce }, data || {} ),
            timeout: 60000
        } );
    }

    /** The server's own message when it sent one (a refused request), otherwise a generic one. */
    function failureMessage( xhr ) {
        var body = xhr && xhr.responseJSON;
        return ( body && body.message ) ? body.message : ( t.error || 'Something went wrong.' );
    }

    function updateBar( bar ) {
        if ( ! bar ) { return; }
        $.each( bar, function ( key, value ) {
            $( '[data-edc-bar="' + key + '"]' ).text( Number( value ).toLocaleString() );
        } );
    }

    function refreshBar() {
        return request( 'bar' ).done( function ( r ) { updateBar( r.bar ); } );
    }

    // ------------------------------------------------- a job's per-page results

    function loadItems( $job, state, paged ) {
        var $box = $job.find( '.edc-job__items' );
        $box.data( 'state', state ).data( 'paged', paged );
        $box.html( '<p class="edc-loading">' + ( t.loading || 'Loading…' ) + '</p>' );

        $job.find( '.edc-state-filter' ).each( function () {
            $( this ).toggleClass( 'is-active', $( this ).data( 'edc-state' ) === state );
        } );

        return request( 'items', { job_id: $job.data( 'job-id' ), state: state, paged: paged } )
            .done( function ( r ) { $box.html( r.html ); } )
            .fail( function ( xhr ) { $box.html( $( '<p class="edc-empty"/>' ).text( failureMessage( xhr ) ) ); } );
    }

    $( document ).on( 'toggle', '.edc-job', function () {
        var $job = $( this );
        if ( this.open && ! $job.data( 'loaded' ) ) {
            $job.data( 'loaded', true );
            loadItems( $job, '', 1 );
        }
    } );

    $( document ).on( 'click', '.edc-state-filter', function () {
        var $job = $( this ).closest( '.edc-job' );
        $job.data( 'loaded', true );
        loadItems( $job, String( $( this ).data( 'edc-state' ) ), 1 );
    } );

    $( document ).on( 'click', '.edc-job__items [data-edc-page]', function () {
        var $job = $( this ).closest( '.edc-job' );
        loadItems( $job, $job.find( '.edc-job__items' ).data( 'state' ) || '', Number( $( this ).data( 'edc-page' ) ) );
    } );

    // Jobs that start open (unfinished) never fire a toggle: load them now.
    $( function () {
        $( '.edc-job[open]' ).each( function () {
            $( this ).data( 'loaded', true );
            loadItems( $( this ), '', 1 );
        } );
    } );

    window.edcJobsApi = { request: request, failureMessage: failureMessage, updateBar: updateBar, refreshBar: refreshBar, loadItems: loadItems };
}( jQuery ) );
