<!DOCTYPE html>

<div class="grid-animation" id="grid-animation-block">
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
    <div class="animationDot"></div>
</div>
<style>
    :root {
        --bg: #ffffff;
        --light: #c7c5ee;
        --mid: #8f8dde;
        --dark: #4a44cf;
        --size: 1.2rem;
        --gap: .15rem;
        --dot-size: calc((var(--size) - (2 * var(--gap))) / 3);
        --transition: 420ms cubic-bezier(0.4, 0, 0.2, 1);
    }
    .grid-animation {
        display: none;
        width: var(--size);
        height: var(--size);
        grid-template-columns: repeat(3, var(--dot-size));
        grid-template-rows: repeat(3, var(--dot-size));
        gap: var(--gap);
        margin-right: 1rem;
    }
    .grid-animation.active {
        display: grid;
    }


    .animationDot {
        width: var(--dot-size);
        height: var(--dot-size);
        border-radius: 50%;
        background: var(--light);

        will-change: transform, background;
    }

    /* different animations */
    .animationDot:nth-child(1) { animation: a1 1.7s infinite ease-in-out; }
    .animationDot:nth-child(2) { animation: a2 2.3s infinite ease-in-out; animation-delay: -0.5s;}
    .animationDot:nth-child(3) { animation: a3 1.9s infinite ease-in-out; animation-delay: 1.0s;}
    .animationDot:nth-child(4) { animation: a4 2.5s infinite ease-in-out; animation-delay: -0.8s;}
    .animationDot:nth-child(5) { animation: a5 1.6s infinite ease-in-out; animation-delay: -1.2s;}
    .animationDot:nth-child(6) { animation: a6 2.1s infinite ease-in-out; }
    .animationDot:nth-child(7) { animation: a7 1.8s infinite ease-in-out; }
    .animationDot:nth-child(8) { animation: a8 2.4s infinite ease-in-out; animation-delay: -1.0s;}
    .animationDot:nth-child(9) { animation: a9 2.0s infinite ease-in-out; animation-delay: -0.8s;}

    /* keyframes */

    @keyframes a1 {
        0%,100% { transform: scale(1); background: var(--light); }
        40% { transform: scale(1.2); background: var(--dark); }
    }

    @keyframes a2 {
        0%,100% { transform: scale(1); background: var(--light); }
        55% { transform: scale(1.15); background: var(--mid); }
    }

    @keyframes a3 {
        0%,100% { transform: scale(1); background: var(--light); }
        30% { transform: scale(1.18); background: var(--dark); }
    }

    @keyframes a4 {
        0%,100% { transform: scale(1); background: var(--light); }
        65% { transform: scale(1.16); background: var(--mid); }
    }

    @keyframes a5 {
        0%,100% { transform: scale(1); background: var(--light); }
        50% { transform: scale(1.3); background: var(--dark); }
    }

    @keyframes a6 {
        0%,100% { transform: scale(1); background: var(--light); }
        45% { transform: scale(1.17); background: var(--mid); }
    }

    @keyframes a7 {
        0%,100% { transform: scale(1); background: var(--light); }
        35% { transform: scale(1.2); background: var(--dark); }
    }

    @keyframes a8 {
        0%,100% { transform: scale(1); background: var(--light); }
        60% { transform: scale(1.18); background: var(--mid); }
    }

    @keyframes a9 {
        0%,100% { transform: scale(1); background: var(--light); }
        42% { transform: scale(1.2); background: var(--dark); }
    }
    /*.animationDot:nth-child(4) { animation-delay: -0.8s; }*/
    /*.animationDot:nth-child(6) { animation-delay: -1.2s; }*/
    /*.animationDot:nth-child(8) { animation-delay: -0.5s; }*/
</style>
{{--<script>--}}
{{--    function initAnimationDots(){--}}
{{--        const animationDots = [...document.querySelectorAll(".animationDot")];--}}
{{--        const states = [--}}
{{--            [0,0,0,0,0,0,0,0,0],--}}
{{--            [0,0,2,0,0,0,0,0,0],--}}
{{--            [1,0,0,0,0,0,0,0,0],--}}
{{--            [0,1,0,2,0,0,0,0,0],--}}
{{--            [2,0,0,1,1,0,0,0,0],--}}
{{--            [1,0,0,2,0,0,0,1,0],--}}
{{--            [2,0,0,0,0,0,0,0,0],--}}
{{--            [0,1,0,0,0,0,0,0,0],--}}
{{--            [0,1,2,0,0,0,0,0,1],--}}
{{--            [0,0,0,1,1,0,2,0,0],--}}
{{--            [1,0,0,0,1,0,0,0,1],--}}
{{--            [0,1,2,0,0,0,0,0,1],--}}
{{--            [0,1,0,0,2,0,0,0,1],--}}
{{--            [0,0,0,0,0,1,2,1,1],--}}
{{--            [0,0,2,1,0,0,0,1,0],--}}
{{--            [1,1,0,1,2,0,0,0,0]--}}
{{--        ];--}}

{{--        let step = 0;--}}

{{--        function render(frame) {--}}
{{--            animationDots.forEach((animationDot, i) => {--}}
{{--                animationDot.classList.remove("mid", "dark");--}}
{{--                if (frame[i] === 1) animationDot.classList.add("mid");--}}
{{--                if (frame[i] === 2) animationDot.classList.add("dark");--}}
{{--            });--}}
{{--        }--}}

{{--        render(states[0]);--}}

{{--        let last = 0;--}}
{{--        const speed = 330;--}}

{{--        function loop(now) {--}}
{{--            if (now - last > speed) {--}}
{{--                step = (step + 1) % states.length;--}}
{{--                render(states[step]);--}}
{{--                last = now;--}}
{{--            }--}}

{{--            requestAnimationFrame(loop);--}}
{{--        }--}}

{{--        requestAnimationFrame(loop);--}}
{{--    }--}}

{{--</script>--}}
