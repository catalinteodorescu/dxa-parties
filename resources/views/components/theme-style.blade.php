{{--
    Suprascrie la runtime variabilele "primary" din app.css cu paleta aleasa in
    Setari > Aspect. Pus in <head> dupa @vite. CSS-ul de aici e in afara
    @layer, deci bate valorile din @layer theme generate de Tailwind, indiferent
    de ordine. Pentru paleta implicita nu emite nimic (valorile din app.css).
--}}
@if (\App\Support\Theme::key() !== \App\Support\Theme::DEFAULT)
    <style id="dxa-theme">:root{ {{ \App\Support\Theme::inlineStyle() }} }</style>
@endif
