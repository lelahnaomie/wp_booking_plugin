/* Smart Booking v4.6 */
(function($){'use strict';

// Read the primary color from CSS custom property set by sb_inline_css()
function sbPrimary(){return getComputedStyle(document.documentElement).getPropertyValue('--p').trim()||'#5B2D8E';}

window.SBApp={
    state:{step:1,service:null,staff:null,date:null,time:null,
           calYear:new Date().getFullYear(),calMonth:new Date().getMonth()+1,
           unavailable:[],bookingResult:null},
    goTo:function(n){
        this.state.step=n;
        $('.sb-panel').hide().removeClass('active');
        $('#sbStep'+n).fadeIn(260).addClass('active');
        updateBubbles(n);
        var w=$('.sb-wrap');if(w.length)$('html,body').animate({scrollTop:w.offset().top-40},300);
    },
    tabTo:function(n){
        var s=this.state;
        if(n<=s.step){this.goTo(n);return;}
        if(n===2&&s.service){this.goTo(2);return;}
        if(n===3&&s.service&&s.date&&s.time){this.goTo(3);return;}
        if(n===4&&s.service&&s.date&&s.time){this.goTo(4);return;}
        showErr('Complete the current step first.');setTimeout(hideErr,2000);
    }
};

function updateBubbles(cur){$('.sb-step-bubble').each(function(){var s=parseInt($(this).data('step'));$(this).removeClass('active done');if(s===cur)$(this).addClass('active');else if(s<cur)$(this).addClass('done');});}
var currency=$('[data-currency]').attr('data-currency')||'FCFA';

$(document).ready(function(){
    // Apply admin-set Step 1 heading (e.g. "Choose a Car", "Select a Room")
    if(typeof SB!=='undefined' && SB.step1_heading){
        $('#sbStep1Title').text(SB.step1_heading);
        var shortLabel=SB.step1_heading.split(' ').slice(0,2).join(' ');
        $('.sb-step-bubble[data-step="1"] small').text(shortLabel);
    }
    setupCalNav();
    setupPolicy();
    // Initialise step 1 without scroll-jump on page load
    updateBubbles(1);
    $('.sb-panel').hide().removeClass('active');
    $('#sbStep1').show().addClass('active');
});

// Days stepper — global so onclick="" in PHP can reach it
window.sbAdjDays=function(delta){
    var $i=$('#sbDaysInput');
    var v=Math.max(1,parseInt($i.val()||1)+delta);
    $i.val(v);
    // Update prices on service cards to reflect days multiplier
    var cur=$('[data-currency]').attr('data-currency')||'FCFA';
    $('.sb-service-card').each(function(){
        var basePrice=parseFloat($(this).data('price'))||0;
        if(basePrice>0){
            var $priceEl=$(this).find('.sb-svc-price');
            var total=Math.round(basePrice*v);
            $priceEl.text(total.toLocaleString()+' '+cur+(v>1?' (×'+v+')':''));
            $priceEl.data('days',v);
        }
    });
};

/*  TABS: click step bubble to navigate  */
$(document).on('click','.sb-step-bubble',function(){window.SBApp.tabTo(parseInt($(this).data('step')));});

/*  POLICY MODAL  */
function setupPolicy(){
    $(document).on('click','#sbOpenPolicy',function(e){e.preventDefault();$('#sbPolicyModal').fadeIn(200);});
    $(document).on('click','#sbClosePolicy,.sb-policy-modal-close',function(){$('#sbPolicyModal').fadeOut(150);});
    $(document).on('click','.sb-modal-overlay',function(e){if(e.target===this)$(this).fadeOut(150);});
    $(document).on('keydown',function(e){if(e.key==='Escape')$('#sbPolicyModal,#sbSvcModal').fadeOut(150);});
}

/*  SERVICE DETAIL POPUP — triggered by "View Details" button  */
$(document).on('click','.sb-view-details-btn',function(e){
    e.preventDefault();
    e.stopPropagation();
    openServiceModal($(this).closest('.sb-service-card'));
});
// Clicking card itself (not the button) directly books if no details needed
$(document).on('click','.sb-service-card',function(e){
    if($(e.target).hasClass('sb-view-details-btn'))return;
    openServiceModal($(this));
});

function openServiceModal($c){
    var id=parseInt($c.data('id')),name=$c.data('name')||$c.find('strong').first().text().trim();
    var price=parseFloat($c.data('price'))||0,depPct=parseFloat($c.data('deposit'))||0;
    var dur=parseInt($c.data('duration'))||60,desc=$c.data('desc')||'';
    var color=$c.data('color')||sbPrimary(),image=$c.data('image')||'';
    var policy=$c.attr('data-policy')||'';
    var galleryRaw=$c.attr('data-gallery')||'';
    var dep=depPct>0?Math.round(price*depPct/100):0;
    var staffList=[];
    var staffOn=(typeof SB!=='undefined'&&parseInt(SB.require_staff||1))===1;
    if(staffOn){try{staffList=JSON.parse($c.attr('data-staff')||'[]');}catch(e){}}

    var staffHtml='';
    if(staffList.length===0){
        staffHtml='';
    } else if(staffList.length===1){
        var st=staffList[0];
        var av=st.image?'<img src="'+esc(st.image)+'" class="sb-svc-staff-av-img" alt="">':'<span class="sb-svc-staff-av-init" style="background:'+esc(st.color||sbPrimary())+'">'+esc(st.name.charAt(0))+'</span>';
        staffHtml='<div class="sb-svc-staff-single">'+av+'<div class="sb-svc-staff-info"><strong>'+esc(st.name)+'</strong>'+(st.bio?'<p>'+esc(st.bio)+'</p>':'')+'</div></div>';
    } else {
        staffHtml='<div class="sb-svc-staff-pick"><label>Choose your specialist:</label><select class="sb-svc-staff-sel" id="sbModalStaffSel"><option value="0" data-name="Any available specialist" data-color="'+sbPrimary()+'">'+'Any available specialist (recommended)</option>';
        staffList.forEach(function(st){staffHtml+='<option value="'+st.id+'" data-name="'+esc(st.name)+'" data-color="'+esc(st.color||sbPrimary())+'" data-bio="'+esc(st.bio||'')+'">'+esc(st.name)+(st.bio?' — '+esc(st.bio.substring(0,45)):'')+'</option>';});
        staffHtml+='</select><div id="sbStaffBioBox" class="sb-staff-bio-box" style="display:none"><p id="sbStaffBioPrev"></p></div></div>';
    }
    var staffSection=staffHtml?'<div class="sb-svc-staff-section"><h4>Your specialist</h4>'+staffHtml+'</div>':'';

    // Full-size hero image or color bar
    var imgHtml=image
        ?'<div class="sb-svc-modal-img sb-svc-modal-img-full" style="background-image:url(\''+esc(image)+'\');background-size:cover;background-position:center;"></div>'
        :'<div class="sb-svc-modal-bar" style="background:'+esc(color)+'"></div>';

    // Gallery strip
    var galleryHtml='';
    if(galleryRaw){
        var gUrls=galleryRaw.split(',').filter(Boolean);
        if(gUrls.length){
            galleryHtml='<div class="sb-svc-gallery">';
            gUrls.forEach(function(u){galleryHtml+='<div class="sb-svc-gal-thumb" style="background-image:url(\''+esc(u.trim())+'\')"></div>';});
            galleryHtml+='</div>';
        }
    }

    var html='<div class="sb-svc-modal-inner">'+imgHtml+galleryHtml+'<div class="sb-svc-modal-body"><h3>'+esc(name)+'</h3><div class="sb-svc-modal-meta"><span>⏱ '+fmtDur(dur)+'</span>'+(price>0?'<span class="sb-svc-meta-price">'+fmt(price)+' '+currency+'</span>':'<span>Free</span>')+(dep>0?'<span class="sb-svc-dep-badge">Deposit: '+fmt(dep)+' '+currency+'</span>':'')+'</div>'+(desc?'<p class="sb-svc-modal-desc">'+esc(desc)+'</p>':'')+(policy?'<div class="sb-svc-policy-link"><a href="#" class="sb-svc-policy-toggle">View service policy &amp; terms</a><div class="sb-svc-policy-box" style="display:none"><p>'+esc(policy)+'</p></div></div>':'')+staffSection+'</div><div class="sb-svc-modal-foot"><button class="sb-btn sb-btn-ghost" id="sbSvcCancel">Cancel</button><button class="sb-btn sb-btn-primary" id="sbSvcSelect" data-id="'+id+'" data-name="'+esc(name)+'" data-price="'+price+'" data-dep="'+depPct+'" data-dur="'+dur+'">Book This &#8594;</button></div></div>';

    if(!$('#sbSvcModal').length)$('<div class="sb-modal-overlay" id="sbSvcModal"></div>').appendTo('body');
    $('#sbSvcModal').html('<div class="sb-modal-box sb-svc-modal-box">'+html+'</div>').fadeIn(200);
    $('#sbSvcModal').data({staff:staffList,single:staffList.length===1?staffList[0]:null});

    var staffHtml='';
    if(staffList.length===0){
        staffHtml=''; // enable_staff is OFF — show nothing
    } else if(staffList.length===1){
        var st=staffList[0];
        var av=st.image?'<img src="'+esc(st.image)+'" class="sb-svc-staff-av-img" alt="">':'<span class="sb-svc-staff-av-init" style="background:'+esc(st.color||sbPrimary())+'">'+esc(st.name.charAt(0))+'</span>';
        staffHtml='<div class="sb-svc-staff-single">'+av+'<div class="sb-svc-staff-info"><strong>'+esc(st.name)+'</strong>'+(st.bio?'<p>'+esc(st.bio)+'</p>':'')+'</div></div>';
    } else {
        staffHtml='<div class="sb-svc-staff-pick"><label>Choose your specialist:</label><select class="sb-svc-staff-sel" id="sbModalStaffSel"><option value="0" data-name="Any available specialist" data-color="'+sbPrimary()+'">'+ ' Any available specialist (recommended)</option>';
        staffList.forEach(function(st){staffHtml+='<option value="'+st.id+'" data-name="'+esc(st.name)+'" data-color="'+esc(st.color||sbPrimary())+'" data-bio="'+esc(st.bio||'')+'">'+esc(st.name)+(st.bio?' — '+esc(st.bio.substring(0,45)):'')+'</option>';});
        staffHtml+='</select><div id="sbStaffBioBox" class="sb-staff-bio-box" style="display:none"><p id="sbStaffBioPrev"></p></div></div>';
    }
    var staffSection = staffHtml ? '<div class="sb-svc-staff-section"><h4>Your specialist</h4>'+staffHtml+'</div>' : '';

    var imgHtml=image?'<div class="sb-svc-modal-img" style="background-image:url(\''+esc(image)+'\')"></div>':'<div class="sb-svc-modal-bar" style="background:'+esc(color)+'"></div>';
    var html='<div class="sb-svc-modal-inner">'+imgHtml+'<div class="sb-svc-modal-body"><h3>'+esc(name)+'</h3><div class="sb-svc-modal-meta"><span>⏱ '+fmtDur(dur)+'</span>'+(price>0?'<span class="sb-svc-meta-price">'+fmt(price)+' '+currency+'</span>':'<span>Free</span>')+(dep>0?'<span class="sb-svc-dep-badge">Deposit: '+fmt(dep)+' '+currency+'</span>':'')+'</div>'+(desc?'<p class="sb-svc-modal-desc">'+esc(desc)+'</p>':'')+(policy?'<div class="sb-svc-policy-link"><a href="#" class="sb-svc-policy-toggle">View service policy &amp; terms</a><div class="sb-svc-policy-box" style="display:none"><p>'+esc(policy)+'</p></div></div>':'')+staffSection+'</div><div class="sb-svc-modal-foot"><button class="sb-btn sb-btn-ghost" id="sbSvcCancel">Cancel</button><button class="sb-btn sb-btn-primary" id="sbSvcSelect" data-id="'+id+'" data-name="'+esc(name)+'" data-price="'+price+'" data-dep="'+depPct+'" data-dur="'+dur+'">Book This &#8594;</button></div></div>';

    if(!$('#sbSvcModal').length)$('<div class="sb-modal-overlay" id="sbSvcModal"></div>').appendTo('body');
    $('#sbSvcModal').html('<div class="sb-modal-box sb-svc-modal-box">'+html+'</div>').fadeIn(200);
    $('#sbSvcModal').data({staff:staffList,single:staffList.length===1?staffList[0]:null});

    $(document).off('click.pol').on('click.pol','.sb-svc-policy-toggle',function(e){
        e.preventDefault();
        var $box=$(this).next('.sb-svc-policy-box');
        $box.slideToggle(180);
        $(this).text($box.is(':visible')?'Hide service policy':'View service policy & terms');
    });
    $(document).off('change.bsel').on('change.bsel','#sbModalStaffSel',function(){
        var bio=$(this).find('option:selected').data('bio')||'';
        if(bio){$('#sbStaffBioBox').show();$('#sbStaffBioPrev').text(bio);}else{$('#sbStaffBioBox').hide();}
    });
    $(document).off('click.svcx').on('click.svcx','#sbSvcCancel',function(){$('#sbSvcModal').fadeOut(150);});
    $(document).off('click.svcs').on('click.svcs','#sbSvcSelect',function(){
        var bid=$(this).data('id'),bn=$(this).data('name'),bp=parseFloat($(this).data('price'))||0,bd=parseFloat($(this).data('dep'))||0,bdur=parseInt($(this).data('dur'))||60;
        var stl=$('#sbSvcModal').data('staff')||[],single=$('#sbSvcModal').data('single');
        var sid=0,sname='Any available specialist',scol=sbPrimary();
        if(single){sid=single.id;sname=single.name;scol=single.color||sbPrimary();}
        else if(stl.length>0){var $sel=$('#sbModalStaffSel');if($sel.length&&parseInt($sel.val())>0){sid=parseInt($sel.val());sname=$sel.find('option:selected').data('name')||sname;scol=$sel.find('option:selected').data('color')||scol;}}
        window.SBApp.state.service={id:bid,name:bn,price:bp,depositPct:bd,duration:bdur};
        window.SBApp.state.staff={id:sid,name:sname,color:scol};
        $('.sb-service-card').removeClass('selected');$('.sb-service-card[data-id="'+bid+'"]').addClass('selected');
        $('#sbSvcModal').fadeOut(150);renderCalendar();window.SBApp.goTo(2);
    });
}

/*  CALENDAR  */
function setupCalNav(){$(document).on('click','#sbCalPrev',function(){adj(-1);});$(document).on('click','#sbCalNext',function(){adj(1);});}
function adj(o){window.SBApp.state.calMonth+=o;if(window.SBApp.state.calMonth<1){window.SBApp.state.calMonth=12;window.SBApp.state.calYear--;}if(window.SBApp.state.calMonth>12){window.SBApp.state.calMonth=1;window.SBApp.state.calYear++;}renderCalendar();}
function renderCalendar(){
    var y=window.SBApp.state.calYear,m=window.SBApp.state.calMonth;
    var mn=['January','February','March','April','May','June','July','August','September','October','November','December'];
    $('#sbCalMonth').text(mn[m-1]+' '+y);$('#sbCalGrid').html('<div class="sb-loading">Loading...</div>');$('#sbSlotsGrid').empty();$('#sbSlotsTitle').text('Select a date');
    $.get(SB.ajax,{action:'sb_get_unavailable',service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,year:y,month:m},function(res){window.SBApp.state.unavailable=res.success?res.data:[];buildCal(y,m);}).fail(function(){window.SBApp.state.unavailable=[];buildCal(y,m);});
}
function buildCal(y,m){
    var today=new Date();today.setHours(0,0,0,0);var first=new Date(y,m-1,1).getDay(),days=new Date(y,m,0).getDate(),h='';
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d){h+='<div class="sb-cal-day-label">'+d+'</div>';});
    for(var i=0;i<first;i++)h+='<div class="sb-cal-day sb-cal-empty"></div>';
    for(var d=1;d<=days;d++){var ds=y+'-'+pad(m)+'-'+pad(d),dt=new Date(y,m-1,d),cls='sb-cal-day';if(dt<today||window.SBApp.state.unavailable.indexOf(ds)!==-1)cls+=' sb-cal-unavail';if(dt.getTime()===today.getTime())cls+=' sb-cal-today';if(window.SBApp.state.date===ds)cls+=' sb-cal-selected';h+='<div class="'+cls+'" data-date="'+ds+'">'+d+'</div>';}
    $('#sbCalGrid').html(h);
}
$(document).on('click','.sb-cal-day:not(.sb-cal-unavail):not(.sb-cal-empty)',function(){window.SBApp.state.date=$(this).data('date');window.SBApp.state.time=null;$('.sb-cal-day').removeClass('sb-cal-selected');$(this).addClass('sb-cal-selected');fetchSlots();});
function fetchSlots(){
    var dl=new Date(window.SBApp.state.date+'T00:00').toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long'});
    $('#sbSlotsTitle').text(dl);$('#sbSlotsGrid').html('<div class="sb-loading">Loading times...</div>');
    $.get(SB.ajax,{action:'sb_get_slots',service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,date:window.SBApp.state.date},function(res){
        if(!res.success||!res.data.length){$('#sbSlotsGrid').html('<p class="sb-notice">No times available on this date.</p>');return;}
        var h='';$.each(res.data,function(i,sl){var cls=sl.available?'sb-slot':'sb-slot sb-slot-taken';h+='<div class="'+cls+'" data-time="'+sl.time+'" data-end="'+(sl.end_time||'')+'" data-label="'+sl.label+'">'+sl.label+'</div>';});$('#sbSlotsGrid').html(h);
    });
}
$(document).on('click','.sb-slot:not(.sb-slot-taken)',function(){$('.sb-slot').removeClass('selected');$(this).addClass('selected');window.SBApp.state.time={time:$(this).data('time'),end_time:$(this).data('end')||'',label:$(this).data('label')};setTimeout(function(){window.SBApp.goTo(3);},350);});

/*  INFO → REVIEW  */
window.SBApp.goToConfirm=function(){
    if(!window.SBApp.state.date||!window.SBApp.state.time){showErr('Please go back and select a date and arrival time.');return;}
    var name=$('#sbCustName').val().trim(),phone=$('#sbCustPhone').val().trim();
    $('.sb-ferr').text('');$('.sb-field-group input').removeClass('is-err');var ok=true;
    if(!name){$('#sbErrName').text('Name required.');$('#sbCustName').addClass('is-err');ok=false;}
    if(!phone){$('#sbErrPhone').text('Phone required.');$('#sbCustPhone').addClass('is-err');ok=false;}
    if(!ok)return;buildReview();window.SBApp.goTo(4);
};
function buildReview(){
    var s=window.SBApp.state,name=$('#sbCustName').val().trim(),phone=$('#sbCustPhone').val().trim(),email=$('#sbCustEmail').val().trim(),notes=$('#sbCustNotes').val().trim();
    var dl=new Date(s.date+'T00:00').toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
    var numDays=parseInt($('#sbDaysInput').val()||1)||1;
    var basePrice=s.service.price;
    var totalPrice=basePrice*numDays;
    var dep=s.service.depositPct>0?Math.round(totalPrice*s.service.depositPct/100):0,bal=totalPrice-dep;
    var staffOn=(typeof SB!=='undefined'&&parseInt(SB.require_staff||1))===1;
    var h='<div class="sb-review-rows">'+rrow('Service',s.service.name)+(staffOn?rrow('Specialist',s.staff.name):'')+rrow('Date',dl)+rrow('Arrival Time',s.time.label+(s.time.end_time?' — '+toAmPm(s.time.end_time):''))+(numDays>1?rrow('Number of Days',numDays+' days'):'')+rrow('Name',name)+rrow('Phone',phone)+(email?rrow('Email',email):'')+(notes?rrow('Notes',notes):'');
    if(totalPrice>0)h+='<div class="sb-review-row sb-rr-total"><span class="sb-rlabel">Total Amount</span><span class="sb-rvalue sb-review-price">'+fmt(totalPrice)+' '+currency+'</span></div>';
    h+='</div>';
    if(dep>0)h+='<div class="sb-deposit-info-box"><div class="sb-dib-title"> Deposit Required</div><div class="sb-dib-row"><span>Deposit ('+s.service.depositPct+'%)</span><strong>'+fmt(dep)+' '+currency+'</strong></div><div class="sb-dib-row sb-dib-bal"><span>Balance on appointment day</span><span>'+fmt(bal)+' '+currency+'</span></div><p class="sb-dib-note">We will contact you to arrange the deposit. Booking confirmed once deposit received.</p></div>';
    $('#sbReviewCard').html(h);
}
function rrow(l,v){return '<div class="sb-review-row"><span class="sb-rlabel">'+esc(l)+'</span><span class="sb-rvalue">'+esc(v)+'</span></div>';}

/*  SUBMIT  */
window.SBApp.submitBooking=function(){
    var $btn=$('#sbConfirmBtn');$btn.prop('disabled',true).find('.sb-btn-label').hide();$btn.find('.sb-btn-spinner').show();hideErr();
    $.post(SB.ajax,{action:'sb_submit_booking',nonce:window.SB_NONCE||SB.nonce,service_id:window.SBApp.state.service.id,staff_id:window.SBApp.state.staff.id,date:window.SBApp.state.date,time:window.SBApp.state.time.time,end_time:window.SBApp.state.time.end_time||'',name:$('#sbCustName').val().trim(),phone:$('#sbCustPhone').val().trim(),email:$('#sbCustEmail').val().trim(),notes:$('#sbCustNotes').val().trim(),sb_days:parseInt($('#sbDaysInput').val()||1)||1},
    function(res){
        $btn.prop('disabled',false).find('.sb-btn-label').show();$btn.find('.sb-btn-spinner').hide();
        if(!res.success){showErr(res.data.msg||'Something went wrong.');return;}
        var d=res.data;window.SBApp.state.bookingResult=d;
        if(d.deposit_required&&d.deposit_amount>0)showPaymentStep(d);else showFinalSuccess(d);
    }).fail(function(){$btn.prop('disabled',false).find('.sb-btn-label').show();$btn.find('.sb-btn-spinner').hide();showErr('Connection error. Please try again.');});
};

/*  PAYMENT  */
function showPaymentStep(d){
    var dep=d.deposit_amount,cur=d.currency||currency;
    var noGateway=!d.paystack_pk&&!d.flw_pk&&!d.campay_enabled;
    var h='<div class="sb-payment-step"><div class="sb-pay-header"><div class="sb-pay-lock"></div><h3>Pay Deposit to Confirm</h3><p>Your booking is saved. Pay the deposit below to confirm it.</p><div class="sb-pay-amount-box"><span class="sb-pay-label">Deposit required</span><span class="sb-pay-amt">'+fmt(dep)+' '+cur+'</span></div></div>';
    if(d.campay_enabled)h+='<button class="sb-pay-btn sb-pay-campay" id="sbPayCampay">📱 Pay via MTN / Orange MoMo (CamPay)</button>';
    if(d.paystack_pk)h+='<button class="sb-pay-btn sb-pay-card" id="sbPayPaystack">💳 Pay by Card (Paystack)</button>';
    if(d.flw_pk)h+='<button class="sb-pay-btn sb-pay-mtn" id="sbPayFlw">📲 MTN / Orange Money (Flutterwave)</button>';
    if(noGateway)h+='<div class="sb-manual-pay-notice"><p>Send <strong>'+fmt(dep)+' '+cur+'</strong> via Mobile Money. We will contact you at <strong>'+esc(d.customer_phone||'your number')+'</strong> with payment details.</p><a href="'+esc(d.wa_url)+'" class="sb-pay-btn sb-pay-wa" target="_blank">💬 Confirm via WhatsApp</a></div>';
    h+='</div>';
    if(!$('#sbPaymentPanel').length)$('<div class="sb-panel" id="sbPaymentPanel"></div>').appendTo('.sb-wrap');
    $('.sb-panel').hide().removeClass('active');$('#sbPaymentPanel').html(h).show().addClass('active');
    $(document).off('click.ps').on('click.ps','#sbPayPaystack',function(){typeof PaystackPop==='undefined'?loadScript('https://js.paystack.co/v1/inline.js',function(){launchPS(d);}):launchPS(d);});
    $(document).off('click.flw').on('click.flw','#sbPayFlw',function(){typeof FlutterwaveCheckout==='undefined'?loadScript('https://checkout.flutterwave.com/v3.js',function(){launchFLW(d);}):launchFLW(d);});
    $(document).off('click.cp').on('click.cp','#sbPayCampay',function(){launchCampay(d);});
}
function launchPS(d){PaystackPop.setup({key:d.paystack_pk,email:d.customer_email||'noemail@smartbooking.cm',amount:Math.round(d.deposit_amount*100),currency:d.currency==='FCFA'?'GHS':d.currency||'GHS',ref:'SB-'+d.booking_id+'-'+Date.now(),callback:function(r){verifyPS(r.reference,d.booking_id);},onClose:function(){}}).openIframe();}
function verifyPS(ref,bid){$('#sbPaymentPanel').html('<div style="padding:40px;text-align:center;color:var(--p)">Verifying payment...</div>');$.post(SB.ajax,{action:'sb_paystack_verify',nonce:window.SB_NONCE||SB.nonce,reference:ref,booking_id:bid},function(res){if(res.success)showFinalSuccess($.extend({},window.SBApp.state.bookingResult,res.data));else alert(res.data.msg||'Verification failed. Ref: '+ref);});}
function launchFLW(d){FlutterwaveCheckout({public_key:d.flw_pk,tx_ref:'SB-'+d.booking_id+'-'+Date.now(),amount:d.deposit_amount,currency:d.currency||'XOF',payment_options:'mobilemoneyfranco,mobilemoneyghana,card',customer:{email:d.customer_email||'noemail@smartbooking.cm',phone_number:d.customer_phone,name:d.customer_name},customizations:{title:'Booking Deposit',description:'#'+d.booking_id},callback:function(data){if(data.status==='successful')verifyFLW(data.transaction_id,d.booking_id);},onclose:function(){}});}
function verifyFLW(txid,bid){$('#sbPaymentPanel').html('<div style="padding:40px;text-align:center;color:var(--p)">Verifying payment...</div>');$.post(SB.ajax,{action:'sb_flw_verify',nonce:window.SB_NONCE||SB.nonce,transaction_id:txid,booking_id:bid},function(res){if(res.success)showFinalSuccess($.extend({},window.SBApp.state.bookingResult,res.data));else alert(res.data.msg||'Verification failed.');});}
function launchCampay(d){
    var phone=d.customer_phone||'';
    var entered=prompt('Enter the MTN or Orange MoMo number to charge (e.g. 671234567 or 237671234567):',phone);
    if(!entered)return;
    $('#sbPaymentPanel').html('<div style="padding:40px;text-align:center;color:var(--p)"><p style="font-size:1.1rem;margin-bottom:8px">📱 Payment request sent!</p><p>Please check your phone and approve the MoMo request.<br>This page will update automatically once payment is confirmed.</p><div class="sb-spinner" style="margin:20px auto"></div></div>');
    $.post(SB.ajax,{action:'sb_campay_initiate',nonce:window.SB_NONCE||SB.nonce,booking_id:d.booking_id,phone:entered},function(res){
        if(!res.success){$('#sbPaymentPanel').html('<div style="padding:30px;text-align:center"><p style="color:#c0392b">'+esc(res.data.msg||'Payment failed.')+'</p><button class="sb-pay-btn sb-pay-campay" id="sbPayCampay" style="margin-top:14px">Try Again</button></div>');$(document).off('click.cp').on('click.cp','#sbPayCampay',function(){launchCampay(d);});return;}
        pollCampay(res.data.reference,d.booking_id,0,d);
    }).fail(function(){$('#sbPaymentPanel').html('<div style="padding:30px;text-align:center;color:#c0392b">Connection error. Please try again.</div>');});
}
var _cpPollTimer=null;
function pollCampay(ref,bid,attempts,d){
    if(_cpPollTimer)clearTimeout(_cpPollTimer);
    if(attempts>20){$('#sbPaymentPanel').html('<div style="padding:30px;text-align:center"><p>Payment timed out. If you approved the request, contact us with booking #'+bid+'.</p><a href="'+esc(d.wa_url)+'" class="sb-pay-btn sb-pay-wa" target="_blank">💬 Contact via WhatsApp</a></div>');return;}
    _cpPollTimer=setTimeout(function(){
        $.post(SB.ajax,{action:'sb_campay_check',nonce:window.SB_NONCE||SB.nonce,booking_id:bid,reference:ref},function(res){
            if(!res.success){
                if(res.data&&res.data.failed){$('#sbPaymentPanel').html('<div style="padding:30px;text-align:center"><p style="color:#c0392b">'+esc(res.data.msg||'Payment failed.')+'</p><button class="sb-pay-btn sb-pay-campay" id="sbPayCampay">Try Again</button></div>');$(document).off('click.cp').on('click.cp','#sbPayCampay',function(){launchCampay(d);});}
                else{pollCampay(ref,bid,attempts+1,d);}
                return;
            }
            if(res.data.paid)showFinalSuccess($.extend({},window.SBApp.state.bookingResult,res.data));
            else pollCampay(ref,bid,attempts+1,d);
        }).fail(function(){pollCampay(ref,bid,attempts+1,d);});
    },4000);
}

/*  SUCCESS  */
function showFinalSuccess(d){
    $('#sbWaBtn').attr('href',d.wa_url||'#');$('#sbPdfBtn').attr('href',d.pdf_url||'#');
    var dep=d.deposit_amount||0,cur=d.currency||currency;
    var msg='Your booking has been received! ';
    if(dep>0&&d.deposit_required)msg+='Deposit confirmed  Your appointment is locked in!';
    else if(dep>0)msg+='We will contact you to arrange the deposit of '+fmt(dep)+' '+cur+'.';
    else msg+='We will confirm your appointment shortly.';
    $('#sbSuccessMsg').text(msg);
    if(d.appointment_rules)$('#sbSuccessRules').html('<div class="sb-rules-box"><h4> Appointment Policy</h4><p>'+esc(d.appointment_rules)+'</p></div>');
    $('.sb-panel').hide().removeClass('active');$('#sbSuccess').fadeIn(350).addClass('active');$('.sb-step-bubble').addClass('done');
}

/*  RESET  */
window.SBApp.reset=function(){
    window.SBApp.state={step:1,service:null,staff:null,date:null,time:null,calYear:new Date().getFullYear(),calMonth:new Date().getMonth()+1,unavailable:[],bookingResult:null};
    $('#sbCustName,#sbCustPhone,#sbCustEmail,#sbCustNotes').val('');
    $('.sb-service-card,.sb-slot').removeClass('selected');$('#sbPaymentPanel').remove();
    $('.sb-panel').hide().removeClass('active');$('#sbStep1').show().addClass('active');
    $('.sb-step-bubble').removeClass('active done');$('.sb-step-bubble[data-step="1"]').addClass('active');
};

/*  UTILS  */
function showErr(m){$('#sbError').text(m).show();}
function hideErr(){$('#sbError').hide().text('');}
function pad(n){return n<10?'0'+n:''+n;}
function fmt(n){return Math.round(n).toLocaleString();}
function esc(s){return $('<div>').text(String(s||'')).html();}
function toAmPm(t){if(!t)return '';var p=t.split(':'),h=parseInt(p[0]),m=p[1],ap=h>=12?'PM':'AM';h=h%12||12;return h+':'+m+' '+ap;}
function fmtDur(min){var daysOn=(typeof SB!=='undefined'&&parseInt(SB.enable_daily_rental||0))===1;if(daysOn){var d=Math.max(1,Math.round(min/1440));return d===1?'1 Day':d+' Days';}if(min<60)return min+' min';var h=Math.floor(min/60),m=min%60;return m?h+'h '+m+'min':h+'h';}
function loadScript(src,cb){var s=document.createElement('script');s.src=src;s.onload=cb;document.head.appendChild(s);}

})(jQuery);
