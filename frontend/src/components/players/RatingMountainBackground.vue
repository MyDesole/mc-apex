<script setup>
/**
 * Декоративный фон рейтинга: ночное небо, горы, туман.
 *
 * Разметка статична и не зависит от данных — вынесена отдельно,
 * потому что занимала 419 строк внутри страницы.
 */
</script>

<template>
        <svg
            class="mountain__bg"
            viewBox="0 0 1400 620"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
          <defs>
            <linearGradient
                id="skyGradient"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#05050d" />
              <stop offset="42%" stop-color="#090a18" />
              <stop offset="72%" stop-color="#111225" />
              <stop offset="100%" stop-color="#080811" />
            </linearGradient>

            <linearGradient
                id="mountainBack"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#20213c" />
              <stop offset="100%" stop-color="#0b0b15" />
            </linearGradient>

            <linearGradient
                id="mountainMid"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#303153" />
              <stop offset="42%" stop-color="#1b1c34" />
              <stop offset="100%" stop-color="#0b0b17" />
            </linearGradient>

            <linearGradient
                id="mountainMain"
                x1="0"
                y1="0"
                x2="1"
                y2="1"
            >
              <stop offset="0%" stop-color="#3c3e67" />
              <stop offset="38%" stop-color="#282a4b" />
              <stop offset="70%" stop-color="#17182e" />
              <stop offset="100%" stop-color="#0a0a14" />
            </linearGradient>

            <linearGradient
                id="leftFace"
                x1="0"
                y1="0"
                x2="1"
                y2="1"
            >
              <stop offset="0%" stop-color="#55577f" stop-opacity=".75" />
              <stop offset="65%" stop-color="#262743" stop-opacity=".45" />
              <stop offset="100%" stop-color="#0b0b15" stop-opacity=".1" />
            </linearGradient>

            <linearGradient
                id="rightFace"
                x1="1"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#10111f" stop-opacity=".9" />
              <stop offset="100%" stop-color="#05050c" stop-opacity=".35" />
            </linearGradient>

            <linearGradient
                id="snow"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#ffffff" stop-opacity=".94" />
              <stop offset="40%" stop-color="#dbeafe" stop-opacity=".7" />
              <stop offset="100%" stop-color="#94a3b8" stop-opacity=".05" />
            </linearGradient>

            <radialGradient
                id="goldGlow"
                cx="50%"
                cy="30%"
                r="55%"
            >
              <stop offset="0%" stop-color="#facc15" stop-opacity=".3" />
              <stop offset="30%" stop-color="#facc15" stop-opacity=".13" />
              <stop offset="70%" stop-color="#facc15" stop-opacity=".025" />
              <stop offset="100%" stop-color="#facc15" stop-opacity="0" />
            </radialGradient>

            <radialGradient
                id="purpleGlow"
                cx="50%"
                cy="50%"
                r="50%"
            >
              <stop offset="0%" stop-color="#8b5cf6" stop-opacity=".2" />
              <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0" />
            </radialGradient>

            <linearGradient
                id="mist"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#c4b5fd" stop-opacity="0" />
              <stop offset="50%" stop-color="#c4b5fd" stop-opacity=".08" />
              <stop offset="100%" stop-color="#c4b5fd" stop-opacity="0" />
            </linearGradient>

            <filter
                id="softBlur"
                x="-30%"
                y="-30%"
                width="160%"
                height="160%"
            >
              <feGaussianBlur stdDeviation="18" />
            </filter>

            <filter
                id="smallBlur"
                x="-30%"
                y="-30%"
                width="160%"
                height="160%"
            >
              <feGaussianBlur stdDeviation="5" />
            </filter>
          </defs>

          <rect
              width="1400"
              height="620"
              fill="url(#skyGradient)"
          />

          <ellipse
              cx="700"
              cy="280"
              rx="500"
              ry="190"
              fill="url(#goldGlow)"
          />

          <ellipse
              cx="700"
              cy="390"
              rx="500"
              ry="170"
              fill="url(#purpleGlow)"
          />

          <circle
              cx="700"
              cy="120"
              r="88"
              fill="#facc15"
              opacity=".035"
              filter="url(#softBlur)"
          />

          <circle
              cx="700"
              cy="120"
              r="46"
              fill="#facc15"
              opacity=".035"
          />

          <g fill="#fff">
            <circle cx="94" cy="76" r="1.1" opacity=".6" />
            <circle cx="158" cy="144" r=".8" opacity=".45" />
            <circle cx="228" cy="62" r="1.5" opacity=".72" />
            <circle cx="315" cy="120" r=".8" opacity=".4" />
            <circle cx="396" cy="54" r="1.1" opacity=".58" />
            <circle cx="478" cy="105" r=".7" opacity=".4" />
            <circle cx="550" cy="42" r="1.3" opacity=".7" />
            <circle cx="626" cy="82" r=".7" opacity=".4" />
            <circle cx="774" cy="72" r=".9" opacity=".5" />
            <circle cx="852" cy="42" r="1.3" opacity=".7" />
            <circle cx="932" cy="118" r=".8" opacity=".45" />
            <circle cx="1024" cy="60" r="1.2" opacity=".65" />
            <circle cx="1104" cy="136" r=".7" opacity=".4" />
            <circle cx="1180" cy="76" r="1.3" opacity=".7" />
            <circle cx="1280" cy="118" r=".8" opacity=".5" />
            <circle cx="1340" cy="58" r="1.1" opacity=".55" />
          </g>

          <g stroke="#fff" stroke-linecap="round">
            <path
                d="M176 88v8M172 92h8"
                stroke-width="1"
                opacity=".4"
            />

            <path
                d="M1118 94v10M1113 99h10"
                stroke-width="1"
                opacity=".5"
            />

            <path
                d="M128 210v8M124 214h8"
                stroke-width="1"
                opacity=".35"
            />
          </g>

          <path
              d="M0 470
               L110 392
               L220 438
               L330 350
               L440 428
               L560 344
               L700 418
               L830 340
               L960 420
               L1080 350
               L1200 430
               L1400 370
               V620
               H0Z"
              fill="url(#mountainBack)"
              opacity=".95"
          />

          <path
              d="M0 620
               L180 382
               L330 510
               L430 620Z"
              fill="url(#mountainBack)"
          />

          <path
              d="M970 620
               L1130 382
               L1400 580
               L1400 620Z"
              fill="url(#mountainBack)"
          />

          <path
              d="M80 620
               L350 282
               L590 620Z"
              fill="url(#mountainMid)"
          />

          <path
              d="M350 282
               L300 350
               L340 338
               L365 370
               L397 335
               L430 362
               L350 282Z"
              fill="url(#snow)"
              opacity=".7"
          />

          <path
              d="M810 620
               L1060 300
               L1340 620Z"
              fill="url(#mountainMid)"
          />

          <path
              d="M1060 300
               L1008 365
               L1048 350
               L1078 380
               L1110 342
               L1140 370
               L1060 300Z"
              fill="url(#snow)"
              opacity=".65"
          />

          <path
              d="M285 620
               L700 74
               L1115 620Z"
              fill="url(#mountainMain)"
          />

          <path
              d="M700 74
               L285 620
               L700 620Z"
              fill="url(#leftFace)"
              opacity=".75"
          />

          <path
              d="M700 74
               L1115 620
               L700 620Z"
              fill="url(#rightFace)"
              opacity=".95"
          />

          <path
              d="M700 74
               L628 170
               L655 158
               L676 190
               L700 176
               L725 194
               L747 158
               L772 174
               Z"
              fill="url(#snow)"
          />

          <path
              d="M700 74
               L653 160
               L680 147
               L700 177
               L724 145
               L750 161Z"
              fill="#fff"
              opacity=".22"
          />

          <path
              d="M700 74 L700 620"
              stroke="#fff"
              stroke-width="1"
              opacity=".055"
          />

          <path
              d="M700 74 L520 620"
              stroke="#fff"
              stroke-width="1"
              opacity=".04"
          />

          <path
              d="M700 74 L880 620"
              stroke="#000"
              stroke-width="1"
              opacity=".12"
          />

          <path
              d="M285 620 L700 74 L1115 620"
              fill="none"
              stroke="#a5b4fc"
              stroke-width="1"
              opacity=".12"
          />

          <path
              d="M520 430 L565 360 L600 420"
              fill="none"
              stroke="#fff"
              stroke-width="2"
              opacity=".06"
          />

          <path
              d="M850 430 L815 365 L780 420"
              fill="none"
              stroke="#fff"
              stroke-width="2"
              opacity=".045"
          />

          <path
              d="M0 550
               C160 500 260 515 390 550
               C530 590 610 570 700 550
               C810 525 920 575 1040 545
               C1170 512 1280 510 1400 550
               V620
               H0Z"
              fill="#06060d"
              opacity=".55"
          />

          <rect
              x="0"
              y="475"
              width="1400"
              height="120"
              fill="url(#mist)"
              filter="url(#smallBlur)"
          />

          <g fill="#fff">
            <circle cx="150" cy="470" r="1" opacity=".2" />
            <circle cx="265" cy="530" r=".8" opacity=".18" />
            <circle cx="420" cy="490" r=".7" opacity=".2" />
            <circle cx="980" cy="500" r=".8" opacity=".18" />
            <circle cx="1150" cy="470" r="1" opacity=".2" />
            <circle cx="1260" cy="525" r=".7" opacity=".15" />
          </g>
        </svg>
</template>

<style>
/* ============================================================
   MOUNTAIN
   ============================================================ */

.mountain {
  position: relative;

  width: 100%;
  min-height: 520px;

  margin-bottom: 42px;

  aspect-ratio: 1400 / 620;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, .07);
  border-radius: 22px;

  background: #070710;

  box-shadow:
      0 35px 70px rgba(0, 0, 0, .4),
      0 10px 25px rgba(0, 0, 0, .25),
      inset 0 1px rgba(255, 255, 255, .04);
}

.mountain::after {
  content: "";

  position: absolute;
  inset: 0;

  pointer-events: none;

  border-radius: inherit;

  box-shadow:
      inset 0 0 80px rgba(0, 0, 0, .45),
      inset 0 -40px 80px rgba(0, 0, 0, .4);
}

.mountain__bg {
  position: absolute;
  inset: 0;

  display: block;

  width: 100%;
  height: 100%;
}

.mountain__vignette {
  position: absolute;
  z-index: 2;
  inset: 0;

  pointer-events: none;

  background:
      radial-gradient(
          ellipse at 50% 20%,
          transparent 0%,
          rgba(0, 0, 0, .04) 45%,
          rgba(0, 0, 0, .48) 100%
      );
}

.mountain__noise {
  position: absolute;
  z-index: 3;
  inset: 0;

  pointer-events: none;

  opacity: .035;

  background-image:
      url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.7'/%3E%3C/svg%3E");
}
</style>
