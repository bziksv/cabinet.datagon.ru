<thead class="scanned-sites-thead">
<tr class="scanned-sites-thead__titles" id="scanned-sites-row">
    <th class="scanned-sites-th-pos">{{ __('Position in the top') }}</th>
    <th class="scanned-sites-th-domain">{{ __('Domain') }}</th>
    <th class="scanned-sites-th-metric">
        {{ __('Total score') }}
        @if($admin)
            <span class="__helper-link ui_tooltip_w">
                <i class="fa fa-question-circle"></i>
                <span class="ui_tooltip __bottom">
                    <span class="ui_tooltip_content" style="width: 300px">
                        Общий балл рассчитывается следующим образом: охват по важным словам + охват по tf + плотность<br>
                        Полученная сумма сначала делится на 3, затем умножается на 2<br>
                        - <br>
                        Если полученное кол-во баллов больше 100, то мы приравниваем его к 100.<br>
                        <br>
                        <span class="text-primary">Эта подсказка видна только админам</span>
                    </span>
                </span>
            </span>
        @endif
    </th>
    <th class="scanned-sites-th-metric scanned-sites-th-wide">
        {{ __('coverage for all important words') }}
        @if($admin)
            <span class="__helper-link ui_tooltip_w">
                <i class="fa fa-question-circle"></i>
                <span class="ui_tooltip __bottom">
                    <span class="ui_tooltip_content" style="width: 300px">
                        Из таблицы униграм берутся все слова (далее эти слова именуются "важные слова") <br>
                        Для каждого отдельно взятого сайта происходит проверка наличия в нём слов, которые считаются важными <br>
                        Если важное слово присутсвует в проверяемом сайте, то он получает за него 1 балл<br>
                        Полученый процент равен сумме полученых баллов делённой на 1000
                        <br>
                        <span class="text-primary">Эта подсказка видна только админам</span>
                    </span>
                </span>
            </span>
        @endif
    </th>
    <th class="scanned-sites-th-metric">
        {{ __('Coverage by tf') }}
        @if($admin)
            <span class="__helper-link ui_tooltip_w">
                <i class="fa fa-question-circle"></i>
                <span class="ui_tooltip __bottom">
                    <span class="ui_tooltip_content" style="width: 300px">
                        Из таблицы униграм берутся все слова и их значения tf(далее эти слова именуются "важные слова") <br>
                        Для каждого отдельно взятого сайта происходит проверка наличия в нём слов, которые считаются важными <br>
                        Если важное слово присутсвует в проверяемом сайте, то он получает за него балл равный tf из таблицы униграм <br>
                        Общая сумма баллов каждого конкретного сайта делиться на общую сумму tf из таблицы униграм, таким образом мы получаем % охвата
                        <br>
                        <span class="text-primary">Эта подсказка видна только админам</span>
                    </span>
                </span>
            </span>
        @endif
    </th>
    <th class="scanned-sites-th-metric">
        {{ __('Width') }}
        @if($admin)
            <span class="__helper-link ui_tooltip_w">
                <i class="fa fa-question-circle"></i>
                <span class="ui_tooltip __bottom">
                    <span class="ui_tooltip_content" style="width: 300px">
                        Для вычисления  ширины, беруться первые 10 не игнорируемых сайтов (позиция в топе) <br>
                        Их охват по всем словам(%) плюсуется и делиться на 10, для того чтобы выявить 100% ширину <br>
                        В соответствии с этими 100% для каждого сайта ширина просчитывается  отдельно
                        <br>
                        <span class="text-primary">Эта подсказка видна только админам</span>
                    </span>
                </span>
            </span>
        @endif
    </th>
    <th class="scanned-sites-th-metric">
        {{ __('Density') }}
        @if($admin)
            <span class="__helper-link ui_tooltip_w">
                <i class="fa fa-question-circle"></i>
                <span class="ui_tooltip __bottom">
                    <span class="ui_tooltip_content" style="width: 300px">
                        Плотность высчитывается от значения средней по ТОПу для КАЖДОЙ ОСНОВНОЙ ФРАЗЫ. <br>
                        Если в средней 20, а у нас 5, то это 25 баллов. <br>
                        Дальше все баллы для всех фраз складываются и делятся на общее количество слов. <br>
                        - <br>
                        Если мы переспамили, то пока в этом варианте мы никак не учитываем этот момент, фраза просто получает 100 баллов по плотности. <br>
                        <br>
                        <span class="text-primary">Эта подсказка видна только админам</span>
                    </span>
                </span>
            </span>
        @endif
    </th>
    <th class="scanned-sites-th-narrow">{{ __('Characters') }}</th>
    <th class="scanned-sites-th-result">{{ __('Result') }}</th>
</tr>
</thead>
