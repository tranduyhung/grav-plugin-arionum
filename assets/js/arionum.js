function showMessage(res) {
  if (res.message) {
    var Toastr = Grav.default.Utils.toastr;

    if (!res.success) {
      Toastr.error(res.message);
    } else {
      Toastr.info(res.message);
    }
  }
}

function getTransactions() {
  var address = $('#walletSelection').find(':selected').val();

  $('#getTransactions .loader-container').show();
  $('#getTransactions .search-icon, #transactionList').hide();
  $('#getTransactions').attr('disabled', 'disabled');
  $('#transactionList tbody').empty();

  $.ajax({
    url: window.GravAdmin.config.current_url + '?task=getTransactions&address=' + address,
    method: 'GET',
    dataType: 'json'
  }).done(function(res) {
    if (res.data) {
      for (var i = 0; i < res.data.length; i++) {
        var r = res.data[i];
        var tr = $('<tr>');
        tr.append('<td class="transaction-id">' + r.id + '</td>');
        tr.append('<td>' + r.type + '</td>');
        tr.append('<td>' + r.date + '</td>');
        tr.append('<td>' + r.value + '</td>');
        $('#transactionList tbody').append(tr);
      }

      $('#transactionList').show();
    }

    showMessage(res);

    $('#getTransactions .loader-container').hide();
    $('#getTransactions .search-icon').show();
    $('#getTransactions').attr('disabled', null);
  });
}

function getTransaction() {
  var transactionId = $('#transactionId').val();

  if (transactionId == '')
    return;

  $('#checkTransaction .loader-container').show();
  $('#checkTransaction .search-icon').hide();
  $('#checkTransaction').attr('disabled', 'disabled');

  $.ajax({
    url: window.GravAdmin.config.current_url + '?task=getTransaction&transaction_id=' + transactionId,
    method: 'GET',
    dataType: 'json'
  }).done(function(res) {
    if (res.data) {
      var ul = $('<ul>');

      for (var i = 0; i < res.data.length; i++) {
        var r = res.data[i];
        var li = $('<li>');
        li.append('<span>' + r.name + ': </span>');
        li.append('<span>' + r.value + '</span>');
        ul.append(li);
      }

      $('#transactionDetail').append(ul);
    }

    showMessage(res);

    $('#checkTransaction .loader-container').hide();
    $('#checkTransaction .search-icon').show();
    $('#checkTransaction').attr('disabled', null);
  });
}

function getExchanges() {
  $('.currency .loader-container').show();

  $.ajax({
    url: window.GravAdmin.config.current_url + '?task=getExchanges',
    method: 'GET',
    dataType: 'json'
  }).done(function(res) {
    if (res.data && res.data.length > 0) {
      var ul = ('#exchanges .widget-content ul');

      for (i = 0; i < res.data.length; i++) {
        var e = res.data[i];

        $('#currency' + e.currency + ' .loader-container').hide();
        $('#currency' + e.currency + ' .value').html(e.value);
      }

      getBalances();
    }

    showMessage(res);
  });
}

function getBalances() {
  var wallets = $('.wallet');

  if (wallets.length == 0)
    return;

  wallets.each(function(i, e) {
    var address = $(e).data('hint');
    var index = $(e).data('index');

    $.ajax({
      url: window.GravAdmin.config.current_url + '?task=getBalance&address=' + address,
      method: 'GET',
      dataType: 'json'
    }).done(function(res) {
      if (res.success) {
        var amount = res.data;
        $('#wallet' + index + 'ARO .loader-container').hide();
        $('#wallet' + index + 'ARO .value').html(amount);

        var currencies = $('.currency');

        if (currencies.length > 0) {
          for (var i = 0; i < currencies.length; i++) {
            var currency = $(currencies[i]);
            var round = currency.data('round');
            var name = currency.data('name');
            var rate = currency.find('.value').html();

            var exchange = amount * rate;

            if (round > 0)
              exchange = exchange.toFixed(round);

            $('#wallet' + index + name + ' .loader-container').hide();
            $('#wallet' + index + name + ' .value').html(exchange);
          }
        }
      }

      showMessage(res);
    });
  });
}

$(document).ready(function() {
  getExchanges();

  $('#refreshExchanges').on('click', function() {
    $('#exchanges .widget-content ul').empty();
    $('#exchanges .widget-loader').show();
    getExchanges();
  });

  $('#refreshWallets').on('click', function() {
    var balance = $('#wallets .widget-content .balance');
    balance.find('span').empty();
    balance.find('i').show();
    getBalances();
  });

  $('#checkTransaction').on('click', function(e) {
    e.preventDefault();
    getTransaction();
  });

  $('#getTransactions').on('click', function(e) {
    e.preventDefault();
    getTransactions();
  })

  $('body').on('click', '#transactionList td.transaction-id', function() {
    $('html, body').animate({
      scrollTop: $("#transactionId").offset().top
    }, 1000);

    var address = $(this).html();
    $('#transactionId').val(address);
    $('#checkTransaction').trigger('click');
  });
});
