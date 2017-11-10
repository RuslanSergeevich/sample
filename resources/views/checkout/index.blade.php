@extends('layouts.app')

@section('pageClass', 'page-checkout')

@section('mainClasses', 'm-b-50')
@section('title', trans('checkout.index.title'))

@section('content')

	<div class="container m-t-20 checkout-step-back">
		@if(Request::session()->has('flow-completion-token'))
			<a href="{{ url()->route('flow',['token' => Request::session()->get('flow-completion-token') ]) }}">{{ trans('checkout.back') }}</a>
		@endif

		@if(Request::session()->has('vitamins'))
			<a href="{{ url()->action('PickMixController@get') }}?selected={{ implode(',', session('vitamins')->toArray()) }}">{{ trans('checkout.back-pick') }}</a>
		@endif
	</div>

	<div class="container m-t-50" id="app">
		<div class="row">
			<div class="col-md-4 visible-sm visible-xs text-center">
				<div class="mobile-total-text">{{ trans('checkout.index.total.total') }}</div>
				<div class="mobile-total">{{ trans('general.money-vue', ['amount' => 'total']) }}</div>

				@if ( ! $giftcard && $product->isSubscription() )
					<div class="m-t-20 m-b-20">
						<a href="#coupon-form-mobile" id="toggle-coupon-form-mobile">{{ trans('checkout.index.coupon.link') }}</a>
					</div>
					<form method="post" action="{{ URL::action('CheckoutController@applyCoupon') }}" id="coupon-form-mobile"
						  style="@if(!Request::old('coupon')) display: none; @endif">
						<div class="row">
							<div class="col-md-7">
								<input type="text" name="coupon" maxlength="20" placeholder="{{ trans('checkout.index.coupon.input-placeholder') }}" data-validate="true"
									   class="input input--regular input--uppercase input--spacing input--full input--semibold"
									   value="{{ Request::old('coupon', Session::get('applied_coupon')) }}" required="required"/>
							</div>
							<div class="col-md-5">
								<button type="submit" class="button button--regular button--full button--green">{{ trans('checkout.index.coupon.button-text') }}</button>
							</div>
						</div>
						{{ csrf_field() }}

						<div id="coupon-form-successes-mobile" class="m-t-10"></div>
						<div id="coupon-form-errors-mobile" class="m-t-10"></div>
					</form>
					<hr/>
				@endif
			</div>
			<div class="col-md-8">
				<h1 style="margin-top: 0;">{{ trans('checkout.index.order.title') }}</h1>

				<form method="post" action="{{ URL::action('CheckoutController@postCheckout') }}" id="checkout-form" autocomplete="on" class="form" novalidate="novalidate">
					<div class="spinner" id="form-loader">
						<div class="rect1"></div>
						<div class="rect2"></div>
						<div class="rect3"></div>
						<div class="rect4"></div>
						<div class="rect5"></div>
					</div>

					@if(Auth::guest())
						<div class="card card--large m-b-30 card-padding-fixer">
							<fieldset>
								<div id="facebookloginbox" class="pull-right text-center">
									<a href="javascript:void(0);" onclick="loginFacebook()"
									   class="button button--blue button--medium m-b-5">{{ trans('checkout.fb-login') }}</a>
									<div>
										<small style="font-size: 90%">{{ trans('checkout.facebook_disclaimer') }}</small>
									</div>
								</div>

								<div class="visible-xs clear"></div>
								<legend class="card_title pull-left">{{ trans('checkout.index.order.info.title') }}</legend>
								<div class="clear"></div>
								<label for="is_company">
									<input id="is_company" type="checkbox" v-model="is_company"/> {{ trans('checkout.index.order.info.is-company') }}
								</label>

								<div class="row">
									<div v-show="is_company">
										<div class="col-md-12">
											<div class="m-t-10"></div>
											<label class="label label--full checkout--label" for="input_info_company">{{ trans('checkout.index.order.info.company') }}</label>
											<input type="text" class="input input--medium input--semibold input--full" id="input_info_company"
												   placeholder="{{ trans('checkout.index.order.info.company-placeholder') }}" name="company"
												   value="{{ Request::old('company', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('company') : '')) }}"/>
										</div>
									</div>
								</div>

								<div class="row m-t-50 m-sm-t-20 m-b-50 m-sm-b-20">
									<div class="col-md-6 m-t-">
										<label class="label label--full checkout--label" for="input_info_f_name">{{ trans('checkout.index.order.info.first_name') }}
											<span class="required">*</span></label>
										<input type="text" class="input input--medium input--semibold input--full @if($errors->has('first_name')) input--error @endif"
											   id="input_info_f_name"
											   data-validate="true" placeholder="{{ App::getLocale() != 'nl' ? trans('checkout.index.order.info.first-name-placeholder') : '' }}" name="first_name" required="required"
											   aria-required="true" value="{{ Request::old('first_name', (Auth::user() && Auth::user()->isUser() ? Auth::user()->customer->getFirstname() : '')) }}"/>
									</div>

									<div class="col-md-6">
										<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
										<label class="label label--full checkout--label" for="input_info_l_name">{{ trans('checkout.index.order.info.last_name') }}
											<span class="required">*</span></label>
										<input type="text" class="input input--medium input--semibold input--full @if($errors->has('last_name')) input--error @endif"
											   id="input_info_l_name"
											   data-validate="true" placeholder="{{ App::getLocale() != 'nl' ? trans('checkout.index.order.info.last-name-placeholder') : '' }}" name="last_name" required="required"
											   aria-required="true" value="{{ Request::old('last_name', (Auth::user() && Auth::user()->isUser() ? Auth::user()->customer->getLastName() : '')) }}"/>
									</div>
								</div>

								<div class="row m-t-50 m-sm-t-20 m-b-50 m-sm-b-20">
									<div class="col-md-6">
										<label class="label label--full checkout--label" for="input_info_email">{{ trans('checkout.index.order.info.email') }}
											<span class="required">*</span></label>
										<input type="email" class="input input--medium input--semibold input--full @if($errors->has('email')) input--error @endif"
											   id="input_info_email"
											   data-validate="true" placeholder="{{ trans('checkout.index.order.info.email-placeholder') }}" name="email" required="required"
											   aria-required="true" value="{{ Request::old('email', (Auth::user() && Auth::user()->isUser() ? Auth::user()->email : '')) }}"/>
									</div>

									<div class="col-md-6">
										<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
										<label class="label label--full checkout--label" for="input_info_email">{{ trans('checkout.index.order.info.repeat-email') }}
											<span class="required">*</span></label>
										<input type="email" class="input input--medium input--semibold input--full @if($errors->has('repeat-email')) input--error @endif"
											   id="input_info_repeat_email"
											   data-validate="false" placeholder="{{ trans('checkout.index.order.info.email-placeholder') }}" name="repeat-email" required="required"
											   aria-required="false"/>
										<label id="input_info_repeat_email-error" class="input--error label--error" for="input_info_repeat_email"></label>
									</div>
								</div>

								<div class="row m-b-50 m-sm-b-20">
									<div class="col-md-6">
										<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
										<label class="label label--full checkout--label" for="input_info_password">{{ trans('checkout.index.order.info.password') }}
											<span class="required">*</span></label>
										<input type="password" class="input input--medium input--semibold input--full @if($errors->has('password')) input--error @endif"
											   id="input_info_password"
											   data-validate="true" placeholder="{{ App::getLocale() != 'nl' ? trans('checkout.index.order.info.password-placeholder') : '' }}" name="password" required="required"
											   aria-required="true"/>
									</div>
									<div class="col-md-6">
										<label class="label label--full checkout--label" for="input_info_phone">{{ trans('checkout.index.order.info.phone') }}</label>
										<input type="text" class="input input--medium input--semibold input--full @if($errors->has('phone')) input--error @endif"
											   id="input_info_phone"
											   placeholder="{{ trans('checkout.index.order.info.phone-placeholder') }}" name="phone"
											   value="{{ Request::old('phone', (Auth::user() && Auth::user()->isUser() ?Auth::user()->getCustomer()->getCustomerAttribute('phone') : '')) }}"/>
									</div>



								</div>

								<div class="row">
									@if(App::getLocale() == "nl")
									<h3 class="require-text">{{ trans('checkout.index.order.info.address.require') }}</h3>
									@endif
									<div class="col-md-3">
										<label class="label label--full checkout--label" for="input_info_address_street">{{ trans('checkout.index.order.info.address.street') }}
											<span class="required">*</span></label>
										<input type="text" class="input input--medium input--semibold input--full @if($errors->has('address_street')) input--error @endif"
											   id="input_info_address_street" data-validate="true" placeholder="{{ trans('checkout.index.order.info.address.street-placeholder') }}"
											   name="address_street" required="required" aria-required="true"
											   value="{{ Request::old('address_street', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('address_line1') : '')) }}"/>
									</div>

										<div class="col-md-3">
											<label class="label label--full checkout--label" for="input_info_address_street">{{ trans('checkout.index.order.info.address.number') }}
												<span class="required">*</span></label>
											<input  class="input input--medium input--semibold input--full @if($errors->has('address_number')) input--error @endif"
												   id="input_info_address_number" data-validate="true" placeholder="{{ trans('checkout.index.order.info.address.number-placeholder') }}"
												   name="address_number" required="required" type="text" aria-required="true"
												   value="{{ Request::old('address_number', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('address_number') : '')) }}"/>
										</div>

										<div class="col-md-3 col-xs-6">
											<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
											<label class="label label--full checkout--label" for="input_info_address_city">{{ trans('checkout.index.order.info.address.zipcode') }}
												<span class="required">*</span></label>
											<input class="input input_info_address_zip input--medium input--semibold input--full @if($errors->has('address_zip')) input--error @endif"
												   id="input_info_address_zip" data-validate="true" placeholder="{{ trans('checkout.index.order.info.address.zipcode-placeholder') }}"
												   name="address_zip" required="required" aria-required="true" @if(App::getLocale() != 'nl') type="number" data-pattern="[0-9]{4}" data-validation="number"  maxlength="4" minlength="4"  @else type="text" @endif
												   value="{{ Request::old('address_zip', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('address_postal') : '')) }}"/>
										</div>

									<div class="col-md-3 col-xs-6">
										<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
										<label class="label label--full checkout--label" for="input_info_address_city">{{ trans('checkout.index.order.info.address.city') }}
											<span class="required">*</span></label>
										<input type="text" class="input input--medium input--semibold input--full @if($errors->has('address_city')) input--error @endif"
											   id="input_info_address_city" data-validate="true" placeholder="{{ App::getLocale() != 'nl' ? trans('checkout.index.order.info.address.city-placeholder') : '' }}"
											   name="address_city" required="required" aria-required="true"
											   value="{{ Request::old('address_city', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('address_city') : '')) }}"/>
									</div>
								</div>
								@if(App::getLocale() == "nl")
									<div class="row m-t-20 m-sm-t-20 m-b-20 m-sm-b-20" >
										<div class="col-md-3 col-xs-6">
											<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
											<label class="label label--full checkout--label" for="input_info_address_city">{{ trans('checkout.index.order.info.address.country') }}
												<span class="required">*</span></label>
											<select  name="address_country" id="country-selector" class="select select--medium select--semibold select--full"
													required="required"
													readonly="readonly"
													aria-required="true" data-validate="true">
												<option selected="selected" value="netherlands">{{ trans("countries.netherlands") }}</option>
												<option value="belgium">{{ trans("countries.belgium") }}</option>
											</select>
										</div>
									</div>
									@endif
								@if(App::getLocale() == "da")
									<div class="row m-t-20 m-sm-t-20 m-b-20 m-sm-b-20" style="display: none!important">
										<div class="col-md-3 col-xs-6">
											<div class="visible-xs visible-sm m-t-50 m-sm-t-20"></div>
											<label class="label label--full checkout--label" for="input_info_address_city">{{ trans('checkout.index.order.info.address.country') }}
												<span class="required">*</span></label>
											<select  name="address_country" id="country-selector" class="select select--medium select--semibold select--full"
													 required="required"
													 readonly="readonly"
													 aria-required="true" data-validate="true">
												@foreach(\App\TaxZone::all() as $zone)
													<option
															@if( Request::old('address_country', (Auth::user() && Auth::user()->isUser() ? Auth::user()->getCustomer()->getCustomerAttribute('address_country', trans('general.tax_zone')) : trans('general.tax_zone'))) == $zone->name ) selected="selected"
															@endif value="{{ $zone->name }}">{{ trans("countries.{$zone->name}") }}</option>
												@endforeach
											</select>
										</div>
									</div>
								@endif
							</fieldset>
						</div>
					@endif

					@include('includes.payment.method')

					<div class="m-b-20 terms_container_box">
						<label class="terms-label">
							<input name="terms_accept" type="checkbox" aria-required="true" data-validate="true" required="required" id="terms_checkbox"  />
							@if ( !$product->isSubscription() && App::getLocale() == 'nl')
								<div>{!! trans('checkout.terms-agree-gift') !!}</div>
							@else
							<div>{!! trans('checkout.terms-agree') !!}</div>
							@endif
						</label>
					</div>

					<div class="visible-xs">
						<div class="form-button-submit-holder">
							<button onsubmit="ga('send', 'event', 'order', 'completed');" class="button button--huge button--green button--full button--rounded" type="submit"
									id="button-submit">{{ trans('checkout.index.order.button-submit-text') }}</button>
							<div class="clear"></div>
						</div>
					</div>

					<div class="hidden-xs">
						<div class="form-button-submit-holder">
							<button onsubmit="ga('send', 'event', 'order', 'completed');" class="button button--huge button--green button--rounded" type="submit"
									id="button-submit">{{ trans('checkout.index.order.button-submit-text') }}</button>

							<div class="clear"></div>

						</div>
						<div class="clear"></div>
					</div>

					@if($product->is_subscription == 1)
						<div class="clear"></div>
						<div class="visible-sm visible-xs">
							{!! trans('checkout.index.disclaimer') !!}
						</div>
					@endif

					{{ csrf_field() }}

					<div class="hidden">
						<input type="hidden" name="product_name" value="{{ Request::old('product_name', Request::get('product_name', session('product_name', 'subscription'))) }}"
							   autocomplete="off"/>
						<textarea name="user_data">{{ $user_data }}</textarea>
					</div>
				</form>
			</div><!-- /Form-->
			<div class="visible-sm visible-xs m-b-50"></div>
			<div class="col-md-4">
				<h3 style="margin-top: 0;" class="m-b-35">{{ trans('checkout.index.total.title') }}</h3>
				<hr class="hr--double"/>

				<table v-cloak class="m-b-40">
					<tbody>
					<tr v-for="item in totals">
						<td>
							<span v-show="!item.showPrice" style="margin-right: 30px"></span>
							@{{ item.name }}
						</td>
						<td>
							<span v-show="!item.showPrice && item.price === 0">-</span>
							<span v-show="item.showPrice || item.price !== 0">
								<span v-show="item.price !== 0">{{ trans('general.money-vue', ['amount' => 'item.price']) }}</span>
								<span v-show="item.price === 0">{{ trans('products.free_shipping') }}</span>
							</span>
						</td>
					</tr>
					<tr v-show="discount.applied">
						<td>@{{ discount.code }}</td>
						<td>
							<div v-show="discount.type == 'amount'">-{{ trans('general.money-vue', ['amount' => 'total_discount']) }}</div>
							<div v-show="discount.type == 'percentage'">-@{{ total_discount }}</div>
							<div v-show="discount.type == 'free_shipping'">-100%</div>
						</td>
					</tr>
					<tr>
						<td>{{ trans('checkout.index.total.taxes') }}</td>
						<td>{{ trans('general.money-vue', ['amount' => 'total_taxes']) }}</td>
					</tr>
					<tr class="row--total">
						<td>{{ trans('checkout.index.total.total') }}</td>
						<td>{{ trans('general.money-vue', ['amount' => 'total']) }}</td>
					</tr>
					</tbody>
				</table>

				<div class="hidden-sm hidden-xs">
					@if ( ! $giftcard && $product->isSubscription()  )
						<div class="m-t-20 m-b-20">
							<a href="#coupon-form" id="toggle-coupon-form">{{ trans('checkout.index.coupon.link') }}</a>
						</div>

						<form method="post" action="{{ URL::action('CheckoutController@applyCoupon') }}" id="coupon-form"
							  style="@if(!Request::old('coupon')) display: none; @endif">
							<div class="row">
								<div class="col-md-7">
									<input type="text" name="coupon" maxlength="20" placeholder="{{ trans('checkout.index.coupon.input-placeholder') }}" data-validate="true"
										   class="input input--regular input--uppercase input--spacing input--full input--semibold"
										   value="{{ Request::old('coupon', Session::get('applied_coupon')) }}" required="required"/>
								</div>
								<div class="col-md-5">
									<button type="submit"  class="button button--regular button--full button--green">{{ trans('checkout.index.coupon.button-text') }}</button>
								</div>
							</div>
							{{ csrf_field() }}

							<div id="coupon-form-successes" class="m-t-10"></div>
							<div id="coupon-form-errors" class="m-t-10"></div>
						</form>

						<hr/>
					@endif

					@if($product->is_subscription == 1)
						<div class="m-b-10">
							{!! trans('checkout.index.disclaimer') !!}
						</div>
					@endif

					<div class="clear"></div>
					<div class="m-t-40">
						<div class="clear"></div>
						@include('includes.promo')

						@include('flow-includes.views.help')
					</div>
				</div>

			</div><!-- /Totals-->

		</div>
	</div>
@endsection
