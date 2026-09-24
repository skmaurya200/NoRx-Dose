{{--
    The site footer.

    The brand, the blurb, the contact details and the social links all come
    from Settings; the categories come from FooterComposer. A social link that
    has not been set is not drawn at all rather than pointing at "#".
--}}
<footer class="foot">
    <div class="shell">
        <div class="foot__grid">
            <div>
                <a href="{{ route('home') }}" class="brand">
                    @include('components.brandmark')
                </a>
                <p class="foot__about">{{ $site->text('brand.footer_about') }}</p>

                @if ($site->socialLinks())
                    <div class="social">
                        @foreach ($site->socialLinks() as $link)
                            <a href="{{ $link['url'] }}" aria-label="{{ $link['label'] }}"
                               target="_blank" rel="noopener noreferrer">
                                {{ ['facebook' => 'f', 'instagram' => '◎', 'x' => '✕',
                                    'youtube' => '▶', 'linkedin' => 'in'][$link['key']] }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <h5>Newsletter</h5>
                <form class="sub" onsubmit="return false">
                    <input type="email" placeholder="Your email address" aria-label="Email address">
                    <button type="submit">Subscribe</button>
                </form>
            </div>

            <div>
                <h5>Categories</h5>
                <ul class="foot__list">
                    {{-- The top seven live categories, bound by FooterComposer.
                         Each opens the shop narrowed to itself. --}}
                    @foreach ($footerCategories ?? [] as $category)
                        <li>
                            <a href="{{ route('shop', ['category' => $category->slug]) }}">{{ $category->name }}</a>
                        </li>
                    @endforeach
                    <li><a href="{{ route('shop') }}">All products</a></li>
                </ul>
            </div>

            <div>
                <h5>Quick links</h5>
                <ul class="foot__list">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('shop') }}">Shop</a></li>
                    <li><a href="{{ route('all-products') }}">All products</a></li>
                    <li><a href="{{ route('blogs') }}">Blogs</a></li>
                    <li><a href="{{ route('reviews') }}">Reviews</a></li>
                    <li><a href="{{ route('cart') }}">Cart</a></li>
                    <li><a href="{{ route('checkout') }}">Checkout</a></li>
                </ul>
            </div>

            <div>
                <h5>Help</h5>
                <ul class="foot__list">
                    <li><a href="{{ route('about') }}">About us</a></li>
                    <li><a href="{{ route('faq') }}">FAQ</a></li>
                    <li><a href="{{ route('shipping-policy') }}">Shipping policy</a></li>

                    @if ($site->has('contact.address'))
                        <li>{{ $site->text('contact.address') }}</li>
                    @endif
                    @if ($site->has('contact.phone'))
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $site->text('contact.phone')) }}">{{ $site->text('contact.phone') }}</a></li>
                    @endif
                    @if ($site->has('contact.email'))
                        <li><a href="mailto:{{ $site->text('contact.email') }}">{{ $site->text('contact.email') }}</a></li>
                    @endif
                    @if ($site->has('contact.hours'))
                        <li>{{ $site->text('contact.hours') }}</li>
                    @endif
                </ul>
                <h5 style="margin-top:22px">Payment methods</h5>
                <div class="pay"><span>VISA</span><span>MC</span><span>AMEX</span></div>
            </div>
        </div>

        <div class="foot__bar">
            <p>&copy; {{ date('Y') }} {{ $site->text('brand.name') }}. {{ $site->text('brand.copyright') }}</p>
            <nav>
                <a href="#">Terms of service</a>
                <a href="#">Cookie policy</a>
                <a href="#">Privacy</a>
            </nav>
        </div>
    </div>
</footer>
