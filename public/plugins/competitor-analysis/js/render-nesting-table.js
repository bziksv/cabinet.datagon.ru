function renderNestingTable(nesting) {
    nesting = nesting || {};
    const mainCount = nesting.mainPageCounter != null ? nesting.mainPageCounter : 0;
    const mainPercent = nesting.mainPagePercent != null ? nesting.mainPagePercent : 0;
    const nestedCount = nesting.nestedPageCounter != null ? nesting.nestedPageCounter : 0;
    const nestedPercent = nesting.nestedPagePercent != null ? nesting.nestedPagePercent : 0;

    $('.mainPageCounter').html(mainCount);
    $('.mainPagePercent').html(mainPercent + '%');
    $('.nestedPageCounter').html(nestedCount);
    $('.nestedPagePercent').html(nestedPercent + '%');
    $('.nested').show();
}
