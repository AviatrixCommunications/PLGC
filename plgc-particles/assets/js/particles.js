/* PLGC Falling Particles Engine — inlined by PHP, do not add outer IIFE */
    function init() {
        var cfg     = (typeof PLGC_PARTICLES !== 'undefined') ? PLGC_PARTICLES : {};
        var canvas  = document.getElementById('plgc-particles-canvas');
        if ( ! canvas ) return;

    var ctx     = canvas.getContext('2d');
    var W       = 0, H = 0;
    var parts   = [];
    var raf     = null;
    var paused  = false;

    // Confetti colors
    var CONFETTI_COLORS = ['#FFAE40','#567915','#8C9B5A','#C2D7FF','#ef4444','#a855f7','#22c55e'];

    // ── Resize ────────────────────────────────────────────────────────────────

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    window.addEventListener('resize', resize, { passive: true });
    resize();

    // ── Particle factory ──────────────────────────────────────────────────────

    function baseSize() {
        // Base sizes per behavior type
        var sizes = { drift: 18, flutter: 22, tumble: 24, bounce: 22, spin: 26 };
        return ( sizes[ cfg.behavior ] || 20 ) * ( cfg.size || 1 );
    }

    function randBetween( a, b ) { return a + Math.random() * ( b - a ); }

    function makeParticle() {
        var symbol = cfg.particles[ Math.floor( Math.random() * cfg.particles.length ) ];
        var sz     = baseSize() * randBetween( 0.6, 1.4 );

        return {
            x:       Math.random() * W,
            y:       -sz - Math.random() * H * 0.3,   // stagger start heights
            size:    sz,
            symbol:  symbol,
            opacity: randBetween( 0.5, cfg.opacity || 0.85 ),
            speed:   randBetween( 0.6, 1.4 ) * ( cfg.speed || 1 ),
            wobble:  randBetween( 0, Math.PI * 2 ),
            wobbleSpeed: randBetween( 0.01, 0.04 ),
            wobbleAmp:   randBetween( 0.5, 2.5 ),
            rot:     Math.random() * Math.PI * 2,
            rotSpeed:randBetween( -0.04, 0.04 ),
            color:   CONFETTI_COLORS[ Math.floor( Math.random() * CONFETTI_COLORS.length ) ],
            w:       randBetween( 6, 14 ),  // confetti rect width
            h:       randBetween( 3, 7 ),   // confetti rect height
        };
    }

    // ── Spawn initial set ─────────────────────────────────────────────────────

    var count = Math.min( Math.max( cfg.count || 40, 5 ), 150 );

    for ( var i = 0; i < count; i++ ) {
        var p = makeParticle();
        p.y = Math.random() * H;  // initial particles spread across screen
        parts.push( p );
    }

    // ── Custom image pre-load ─────────────────────────────────────────────────

    var customImg = null;
    if ( cfg.type === 'custom' && cfg.customImage ) {
        customImg = new Image();
        customImg.crossOrigin = 'anonymous';
        customImg.src = cfg.customImage;
    }

    // ── Draw functions per type ───────────────────────────────────────────────

    function drawEmoji( p ) {
        ctx.save();
        ctx.globalAlpha = p.opacity;
        ctx.font = p.size + 'px serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.translate( p.x, p.y );
        if ( cfg.behavior === 'tumble' ) {
            ctx.rotate( p.rot );
        }
        ctx.fillText( p.symbol, 0, 0 );
        ctx.restore();
    }

    function drawText( p ) {
        // Snowflake chars rendered as text
        ctx.save();
        ctx.globalAlpha = p.opacity;
        ctx.font = 'bold ' + p.size + 'px serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = cfg.behavior === 'drift' ? ( cfg.color || '#a8d4f5' ) : '#fff';
        ctx.translate( p.x, p.y );
        ctx.rotate( p.rot );
        ctx.fillText( p.symbol, 0, 0 );
        ctx.restore();
    }

    function drawConfetti( p ) {
        ctx.save();
        ctx.globalAlpha = p.opacity;
        ctx.fillStyle = p.color;
        ctx.translate( p.x, p.y );
        ctx.rotate( p.rot );
        ctx.fillRect( -p.w / 2, -p.h / 2, p.w, p.h );
        ctx.restore();
    }

    function drawCustomImage( p ) {
        if ( ! customImg || ! customImg.complete ) return;
        ctx.save();
        ctx.globalAlpha = p.opacity;
        ctx.translate( p.x, p.y );
        ctx.rotate( p.rot );
        ctx.drawImage( customImg, -p.size / 2, -p.size / 2, p.size, p.size );
        ctx.restore();
    }

    // ── Update & draw loop ────────────────────────────────────────────────────

    var wind = cfg.wind || 0;

    function update() {
        for ( var i = 0; i < parts.length; i++ ) {
            var p = parts[i];

            // Fall
            p.y += p.speed * 0.8;

            // Horizontal wobble
            p.wobble += p.wobbleSpeed;
            var wobble_x;

            switch ( cfg.behavior ) {
                case 'flutter':
                    wobble_x = Math.sin( p.wobble ) * p.wobbleAmp * 1.5;
                    break;
                case 'tumble':
                    wobble_x = Math.sin( p.wobble ) * p.wobbleAmp * 0.8;
                    p.rot   += p.rotSpeed;
                    break;
                case 'spin':
                    wobble_x = Math.sin( p.wobble ) * p.wobbleAmp * 0.5;
                    p.rot   += p.rotSpeed * 2;
                    break;
                case 'bounce':
                    wobble_x = Math.sin( p.wobble ) * p.wobbleAmp * 0.4;
                    p.rot   += p.rotSpeed * 0.5;
                    break;
                default: // drift (snowflakes)
                    wobble_x = Math.sin( p.wobble ) * p.wobbleAmp;
                    p.rot   += p.rotSpeed * 0.3;
                    break;
            }

            p.x += wobble_x + wind;

            // Wrap horizontal
            if ( p.x < -p.size * 2 ) p.x = W + p.size;
            if ( p.x > W + p.size * 2 ) p.x = -p.size;

            // Reset when off bottom
            if ( p.y > H + p.size * 2 ) {
                var fresh = makeParticle();
                parts[i] = fresh;
            }
        }
    }

    function draw() {
        ctx.clearRect( 0, 0, W, H );

        for ( var i = 0; i < parts.length; i++ ) {
            var p = parts[i];

            if ( cfg.type === 'custom' ) {
                drawCustomImage( p );
            } else if ( cfg.type === 'confetti' ) {
                drawConfetti( p );
            } else if ( p.symbol.length <= 2 && /[A-Za-z❄❅❆]/.test(p.symbol) ) {
                drawText( p );
            } else {
                drawEmoji( p );
            }
        }
    }

    // ── Animation loop ────────────────────────────────────────────────────────

    function tick() {
        if ( ! paused ) {
            update();
            draw();
        }
        raf = requestAnimationFrame( tick );
    }

    // ── Pause on hidden tab (performance) ────────────────────────────────────

    document.addEventListener('visibilitychange', function () {
        paused = document.hidden;
    });

    // ── Reduced motion — skip animation entirely ──────────────────────────────

    if ( window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ) {
        canvas.remove();
        return;
    }

    tick();

    } // end init()

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
