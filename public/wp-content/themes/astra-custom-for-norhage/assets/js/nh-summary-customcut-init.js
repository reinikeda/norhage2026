/* NH Price Summary — init block for CUSTOM-CUT SIMPLE products (safe for variable too) */
(function($){
  $(function(){
    if (!window.NHPriceSummary) return;

    // If this is a variable product, do NOT initialize perm² from NH_PS_INIT
    // Variable custom-cut should be driven by found_variation in custom-cutting.js
    if ($('form.variations_form').length) return;

    // If init data isn't present, do nothing
    if (!window.NH_PS_INIT) return;

    var F = NHPriceSummary.fmt || (n => String(n));
    var rg = +NH_PS_INIT.perm2_reg  || 0;
    var sl = +NH_PS_INIT.perm2_sale || 0;
    var fee = +NH_PS_INIT.cut_fee   || 0;

    var perm2HTML =
      (rg>0 && sl>0 && sl<rg) ? ('<del>'+F(rg)+'</del> <ins>'+F(sl)+'</ins>') :
      (sl>0 ? ('<ins>'+F(sl)+'</ins>') :
      (rg>0 ? ('<ins>'+F(rg)+'</ins>') : '—'));

    var cutHTML = fee>0 ? F(fee) : '—';

    // Do NOT force unit/total here; custom-cutting.js will compute them once dimensions are entered
    NHPriceSummary.update({ perm2_html: perm2HTML, cutfee_html: cutHTML });

    $('#nh-price-summary .nh-ps-perm2').css('display','flex');
    $('#nh-price-summary .nh-ps-cutfee').css('display', fee>0 ? 'flex' : '');
  });
})(jQuery);
