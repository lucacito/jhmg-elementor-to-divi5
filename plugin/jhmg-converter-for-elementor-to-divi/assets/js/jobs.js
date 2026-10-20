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

    // <details> fires "toggle" without bubbling, so a delegated jQuery handler never
    // sees it; a capture-phase listener on the document does.
    document.addEventListener( 'toggle', function ( e ) {
        var el = e.target;
        if ( ! el.classList || ! el.classList.contains( 'edc-job' ) || ! el.open ) { return; }
        var $job = $( el );
        if ( ! $job.data( 'loaded' ) ) {
            $job.data( 'loaded', true );
            loadItems( $job, '', 1 );
        }
    }, true );

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


    // ------------------------------------------------------- progress painting

    function paint( $job, ui ) {
        if ( ! ui ) { return; }
        $job.find( '.edc-progress__fill' ).css( 'width', ui.percent + '%' );
        $job.find( '.edc-job__counts' ).text( ui.counts );
        $job.find( '.edc-job__failed' ).text( ui.failed );
        $job.find( '.edc-job__status' ).text( ui.status_label )
            .attr( 'class', 'edc-job__status edc-job__status--' + ui.status );
        $job.attr( 'class', $job.attr( 'class' ).replace( /edc-job--\S+/, 'edc-job--' + ui.status ) ).attr( 'data-status', ui.status );
    }

    function setLive( text ) { $( '.edc-bar__live' ).text( text || '' ); }

    // ------------------------------------------------------------- the runner

    var runningJob = null;

    function later( fn, ms ) { return window.setTimeout( fn, ms ); }

    /**
     * Runs a job one server-sized chunk at a time. Each chunk is a separate
     * request that finishes inside the host's time limit, so a closed tab or a
     * dropped connection just pauses the job: it is all on the server.
     */
    function runJob( jobId ) {
        if ( runningJob ) { return; }
        runningJob = jobId;

        var $job = $( '.edc-job[data-job-id="' + jobId + '"]' );
        var failures = 0;

        setLive( t.converting );
        $job.find( '[data-edc-action="resume"]' ).prop( 'disabled', true );

        function stop( message ) {
            runningJob = null;
            setLive( '' );
            $job.find( '.edc-job__note' ).text( message || '' );
            ensureResume( $job );
        }

        ( function step() {
            request( 'run', { job_id: jobId } ).done( function ( r ) {
                failures = 0;
                paint( $job, r.ui );
                updateBar( r.bar );

                if ( r.busy ) {
                    setLive( t.busy );
                    later( step, 3000 );
                    return;
                }

                setLive( t.converting + ' ' + r.ui.counts );
                $job.find( '.edc-job__note' ).text( '' );

                if ( r.summary.done ) {
                    runningJob = null;
                    setLive( t.finished + ' ' + r.ui.counts );
                    refreshJob( jobId );
                    refreshPendingSection();
                    return;
                }

                step();
            } ).fail( function ( xhr ) {
                // 4xx: the server understood and refused (expired nonce, job gone) — retrying cannot help.
                if ( xhr.status >= 400 && xhr.status < 500 ) { stop( failureMessage( xhr ) ); return; }
                // 5xx / timeout / offline: the chunk may or may not have landed; the server copes with both.
                if ( ++failures <= 3 ) { later( step, 2000 * failures ); return; }
                stop( t.connection );
            } );
        }() );
    }

    /** After a stop the server still says "running" for a couple of minutes; offer Resume right away. */
    function ensureResume( $job ) {
        if ( $job.find( '[data-edc-action="resume"]' ).length ) {
            $job.find( '[data-edc-action="resume"]' ).prop( 'disabled', false );
            return;
        }
        $( '<button type="button" class="button button-primary" data-edc-action="resume"/>' )
            .attr( 'data-job-id', $job.data( 'job-id' ) ).text( t.resume || 'Resume' )
            .prependTo( $job.find( '.edc-job__actions' ) );
    }

    $( document ).on( 'click', '[data-edc-action="resume"]', function () {
        runJob( Number( $( this ).data( 'job-id' ) ) );
    } );

    // A job that was running when the page loaded: carry on from where it is.
    $( function () {
        var $live = $( '.edc-job[data-status="running"]' ).first();
        if ( $live.length ) { runJob( Number( $live.data( 'job-id' ) ) ); }
    } );

    // ----------------------------------------------------------- pending view

    var pending = { types: [], search: '', paged: 1 };
    var selected = {};

    function selectedIds() { return Object.keys( selected ).map( Number ); }

    function syncSelection() {
        var n = selectedIds().length;
        $( '.edc-selected-count' ).text( n );
        $( '[data-edc-action="convert-selected"], [data-edc-action="mark-selected"]' ).prop( 'disabled', n === 0 );
        $( '#edc-pending-table input[name="post_ids[]"]' ).each( function () {
            this.checked = !! selected[ this.value ];
        } );
        $( '#edc-select-all' ).prop( 'checked',
            $( '#edc-pending-table input[name="post_ids[]"]' ).length > 0 &&
            $( '#edc-pending-table input[name="post_ids[]"]:not(:checked)' ).length === 0 );
    }

    /**
     * Re-renders the whole Pending section (keeping the filters and whether it was
     * folded). Used wherever counts can change for reasons the table alone cannot
     * show: the empty state has no table, and the failed-pages note moves too.
     */
    function refreshPendingSection() {
        var $old = $( '#edc-pending' );
        if ( ! $old.length ) { return $.Deferred().resolve().promise(); }
        var wasOpen = $old[ 0 ].open;

        return request( 'pending', { section: 1, types: pending.types, search: pending.search, paged: pending.paged } )
            .done( function ( r ) {
                var $new = $( r.html );
                $new.prop( 'open', wasOpen );
                $old.replaceWith( $new );
                $( '.edc-chip' ).removeClass( 'is-active' )
                    .filter( function () { return String( $( this ).data( 'edc-type' ) ) === ( pending.types[ 0 ] || '' ); } ).addClass( 'is-active' );
                syncSelection();
            } );
    }

    function loadPending() {
        var $table = $( '#edc-pending-table' );
        if ( ! $table.length ) { return refreshPendingSection(); }
        $table.css( 'opacity', 0.5 );

        return request( 'pending', { types: pending.types, search: pending.search, paged: pending.paged } )
            .done( function ( r ) {
                $table.html( r.html ).css( 'opacity', 1 );
                if ( r.actions ) { $( '.edc-pending__actions' ).replaceWith( r.actions ); }
                $( '#edc-pending > summary h2' ).text( function ( _, old ) { return old.replace( /[\d,.]+/, Number( r.count ).toLocaleString() ); } );
                syncSelection();
            } )
            .fail( function ( xhr ) { $table.css( 'opacity', 1 ).html( $( '<p class="edc-empty"/>' ).text( failureMessage( xhr ) ) ); } );
    }

    $( document ).on( 'change', '#edc-pending-table input[name="post_ids[]"]', function () {
        if ( this.checked ) { selected[ this.value ] = true; } else { delete selected[ this.value ]; }
        syncSelection();
    } );

    $( document ).on( 'change', '#edc-select-all', function () {
        var on = this.checked;
        $( '#edc-pending-table input[name="post_ids[]"]' ).each( function () {
            if ( on ) { selected[ this.value ] = true; } else { delete selected[ this.value ]; }
        } );
        syncSelection();
    } );

    $( document ).on( 'click', '.edc-chip', function () {
        var type = String( $( this ).data( 'edc-type' ) );
        pending.types = type ? [ type ] : [];
        pending.paged = 1;
        $( '.edc-chip' ).removeClass( 'is-active' );
        $( this ).addClass( 'is-active' );
        loadPending();
    } );

    function doSearch() { pending.search = $.trim( $( '#edc-pending-search' ).val() ); pending.paged = 1; loadPending(); }
    $( document ).on( 'click', '[data-edc-action="pending-search"]', doSearch );
    $( document ).on( 'keydown', '#edc-pending-search', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); doSearch(); } } );

    $( document ).on( 'click', '[data-edc-pending-page]', function () {
        pending.paged = Number( $( this ).data( 'edc-pending-page' ) );
        loadPending();
    } );

    // ------------------------------------------------------------ start panel

    var $panel = function () { return $( '#edc-start-panel' ); };

    function fmt( template, n ) { return template.replace( '%s', Number( n ).toLocaleString() ); }

    function removeUpsell() { $( '.edc-upsell-inline' ).remove(); }

    function showSelectedUpsell( count ) {
        removeUpsell();
        $( '<p class="edc-upsell-inline"/>' )
            .append( $( '<a class="button button-primary" target="_blank" rel="noopener"/>' ).attr( 'href', cfg.proUrl ).text( fmt( t.upsellSelected, count ) ) )
            .append( document.createTextNode( ' ' + ( t.upsellNote || '' ) ) )
            .insertAfter( '.edc-pending__actions' );
    }

    /**
     * spec = { remaining: bool, ids: [], count: n }. Past the licence limit the
     * answer is the upsell, not a panel the server would then refuse. Past the
     * bulk threshold the user has to pick a mode (Replace is preselected) and
     * tick a confirmation before Start enables.
     */
    function openStart( spec ) {
        removeUpsell();
        if ( spec.count > Number( cfg.limit ) ) { showSelectedUpsell( spec.count ); return; }

        var bulk = spec.count > Number( cfg.bulkThreshold );
        var $p = $panel();

        $p.data( 'spec', spec );
        $p.find( '.edc-start-panel__summary' ).text( spec.count === 1 ? t.summaryOne : fmt( t.summary, spec.count ) );
        $p.find( '.edc-start-panel__error' ).text( '' );
        $p.find( 'input[name="edc_mode"]' ).prop( 'checked', false )
            .filter( '[value="' + ( bulk ? 'replace' : 'copy' ) + '"]' ).prop( 'checked', true );
        $p.find( 'input[name="edc_confirm"]' ).prop( 'checked', false );
        $p.find( '.edc-start-panel__confirm' ).prop( 'hidden', ! bulk );
        syncPanel();
        $p.prop( 'hidden', false )[ 0 ].scrollIntoView( { block: 'nearest' } );
    }

    function syncPanel() {
        var $p = $panel();
        var spec = $p.data( 'spec' ) || { count: 0 };
        var mode = $p.find( 'input[name="edc_mode"]:checked' ).val();
        var bulk = spec.count > Number( cfg.bulkThreshold );

        $p.find( '.edc-start-panel__warning' ).prop( 'hidden', mode !== 'replace' );
        if ( bulk ) {
            $p.find( '.edc-start-panel__confirm span' ).text( fmt( mode === 'replace' ? t.confirmReplace : t.confirmCopy, spec.count ) );
        }
        $p.find( '[data-edc-action="start-job"]' ).prop( 'disabled', ! mode || ( bulk && ! $p.find( 'input[name="edc_confirm"]' ).prop( 'checked' ) ) );
    }

    $( document ).on( 'change', '#edc-start-panel input', syncPanel );

    $( document ).on( 'click', '[data-edc-action="convert-selected"]', function () {
        var ids = selectedIds();
        if ( ids.length ) { openStart( { remaining: false, ids: ids, count: ids.length } ); }
    } );

    $( document ).on( 'click', '[data-edc-action="convert-all"]', function () {
        openStart( { remaining: true, ids: [], count: Number( $( this ).data( 'count' ) ) } );
    } );

    $( document ).on( 'click', '[data-edc-action="cancel-start"]', function () { $panel().prop( 'hidden', true ); } );

    $( document ).on( 'click', '[data-edc-action="start-job"]', function () {
        var $p = $panel();
        var spec = $p.data( 'spec' );
        var mode = $p.find( 'input[name="edc_mode"]:checked' ).val();
        var $btn = $( this );

        if ( ! mode ) { $p.find( '.edc-start-panel__error' ).text( t.chooseMode ); return; }

        $btn.prop( 'disabled', true );
        var data = { mode: mode, confirmed: $p.find( 'input[name="edc_confirm"]' ).prop( 'checked' ) ? '1' : '0', types: pending.types };
        if ( spec.remaining ) { data.remaining = 1; } else { data.post_ids = spec.ids; }

        request( 'create', data ).done( function ( r ) {
            if ( ! r.ok ) {
                $p.find( '.edc-start-panel__error' ).text( r.message || t.error );
                if ( r.code === 'pro_required' && r.upsell ) { showSelectedUpsell( r.count ); }
                syncPanel();
                return;
            }

            $p.prop( 'hidden', true );
            selected = {};
            syncSelection();
            updateBar( r.bar );

            $( '#edc-jobs-list .edc-empty' ).remove();
            var $job = $( r.job_html ).prependTo( '#edc-jobs-list' );
            $job.data( 'loaded', true );
            loadItems( $job, '', 1 );
            $job[ 0 ].scrollIntoView( { block: 'nearest' } );
            refreshPendingSection();
            runJob( r.job_id );
        } ).fail( function ( xhr ) {
            $p.find( '.edc-start-panel__error' ).text( failureMessage( xhr ) );
            syncPanel();
        } );
    } );


    // ----------------------------------------------- retry, and fixing by hand

    /** Re-renders a job's header and buttons from the server, keeping it open if it was. */
    function refreshJob( jobId ) {
        var $old = $( '.edc-job[data-job-id="' + jobId + '"]' );
        var wasOpen = $old.length && $old[ 0 ].open;

        return request( 'job', { job_id: jobId } ).done( function ( r ) {
            var $new = $( r.html );
            $old.replaceWith( $new );
            if ( wasOpen ) {
                $new.prop( 'open', true ).data( 'loaded', true );
                loadItems( $new, '', 1 );
            }
        } );
    }

    function jobIdOf( el ) { return Number( $( el ).closest( '.edc-job' ).data( 'job-id' ) ); }

    function retry( jobId, postIds ) {
        var data = { job_id: jobId };
        if ( postIds ) { data.post_ids = postIds; }

        return request( 'retry', data ).done( function ( r ) {
            updateBar( r.bar );
            refreshPendingSection();
            refreshJob( jobId ).always( function () { if ( r.retried > 0 ) { runJob( jobId ); } } );
        } ).fail( function ( xhr ) {
            $( '.edc-job[data-job-id="' + jobId + '"] .edc-job__note' ).text( failureMessage( xhr ) );
        } );
    }

    $( document ).on( 'click', '[data-edc-action="retry-failed"]', function () { retry( jobIdOf( this ) ); } );
    $( document ).on( 'click', '[data-edc-action="retry-item"]', function () {
        retry( jobIdOf( this ), [ Number( $( this ).data( 'post-id' ) ) ] );
    } );

    /** Marks (or, with undo, un-marks) pages and brings every view that shows them up to date. */
    function mark( ids, undo, $from ) {
        return request( 'mark', { post_ids: ids, undo: undo ? '1' : '0' } ).done( function ( r ) {
            updateBar( r.bar );
            var $job = $from && $from.closest( '.edc-job' );
            if ( $job && $job.length ) { refreshJob( Number( $job.data( 'job-id' ) ) ); }
            refreshPendingSection();
        } ).fail( function ( xhr ) { setLive( failureMessage( xhr ) ); } );
    }

    $( document ).on( 'click', '[data-edc-action="mark-converted"]', function () {
        mark( [ Number( $( this ).data( 'post-id' ) ) ], false, $( this ) );
    } );

    $( document ).on( 'click', '[data-edc-action="unmark-converted"]', function () {
        mark( [ Number( $( this ).data( 'post-id' ) ) ], true, $( this ) );
    } );

    $( document ).on( 'click', '[data-edc-action="mark-selected"]', function () {
        var ids = selectedIds();
        if ( ! ids.length || ! window.confirm( ( t.confirmMark || 'Mark %s pages as converted?' ).replace( '%s', ids.length ) ) ) { return; }
        selected = {};
        mark( ids, false, null );
    } );

    window.edcJobsApi = { runJob: runJob, loadPending: loadPending, refreshPendingSection: refreshPendingSection, request: request, failureMessage: failureMessage, updateBar: updateBar, refreshBar: refreshBar, loadItems: loadItems };
}( jQuery ) );
