import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Navbar from '../components/Navbar'

const Home = () => {
  const { user } = useAuth()

  useEffect(() => {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active')
        }
      })
    }, observerOptions)

    const revealElements = document.querySelectorAll('.scroll-reveal')
    revealElements.forEach(el => observer.observe(el))

    return () => observer.disconnect()
  }, [])

  return (
    <div className="bg-bg-color min-h-screen flex flex-col font-outfit overflow-x-hidden">
      <div className="relative flex flex-col flex-grow">
        {/* Background images */}
        <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
          <img
            src="/img/hero-landing.png"
            alt="Background"
            className="absolute top-24 right-0 w-[90%] sm:w-[70%] md:w-[500px] md:right-[-100px] lg:right-0 lg:w-auto md:h-[500px] lg:h-[800px] lg:top-[100px] opacity-30 sm:opacity-40 md:opacity-60 lg:opacity-90 animate-float object-cover md:object-contain"
          />
          <img
            src="/img/about-friends.png"
            alt="About"
            className="absolute top-[1800px] left-0 w-1/3 opacity-80 animate-float hidden lg:block"
          />
          <img
            src="/img/blubber.png"
            alt="Decoration"
            className="absolute top-[1800px] right-0 w-1/6 opacity-80 animate-float hidden lg:block"
          />
        </div>

        <div className="relative z-10 w-full">
          <div className="px-4 md:px-10 lg:px-20 mx-auto">
            <Navbar />
          </div>
        </div>

        <main className="relative z-10 flex-grow w-full px-4 md:px-10 lg:px-20 mx-auto">
          {/* Hero Section */}
          <div className="mt-16 md:mt-28 lg:mt-40 w-full md:w-3/4 lg:w-1/2 relative z-10 mb-32 scroll-reveal">
            <div className="pr-0 md:pr-6">
              <h1 className="font-poetsen text-darkest-purple text-4xl md:text-5xl lg:text-6xl mb-4 animate-slide-up">
                A Cozy Corner For Everyday Creatives.
              </h1>
              <p className="font-poetsen text-black text-base md:text-lg pr-0 md:pr-12 leading-relaxed animate-fade-in">
                CR8 is a mall-based rental space where creatives can showcase their work and engage in meet-ups, collaborations, and workshops.
              </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-4 mt-10">
              <Link
                to="/shop"
                className="gradient-btn text-white font-outfit font-bold py-4 px-20 rounded-full text-center hover:scale-105 transition duration-300 ease-in-out"
              >
                BUY PRODUCTS
              </Link>
              {user?.role === 'artist' ? (
                <Link
                  to="/dashboard"
                  className="font-outfit font-bold py-4 px-20 rounded-full text-center border-2 border-[#EB5757] text-[#EB5757] bg-transparent hover:bg-[#EB5757] hover:text-white transition duration-300 ease-in-out"
                >
                  SELL PRODUCTS
                </Link>
              ) : user ? (
                <Link
                  to="/artist-application"
                  className="font-outfit font-bold py-4 px-20 rounded-full text-center border-2 border-[#EB5757] text-[#EB5757] bg-transparent hover:bg-[#EB5757] hover:text-white transition duration-300 ease-in-out"
                >
                  SELL PRODUCTS
                </Link>
              ) : (
                <Link
                  to="/login"
                  onClick={() => localStorage.setItem('redirectAfterLogin', JSON.stringify({ action: 'sellProducts' }))}
                  className="font-outfit font-bold py-4 px-20 rounded-full text-center border-2 border-[#EB5757] text-[#EB5757] bg-transparent hover:bg-[#EB5757] hover:text-white transition duration-300 ease-in-out"
                >
                  SELL PRODUCTS
                </Link>
              )}
            </div>
          </div>

          {/* Services Section */}
          <div className="mt-32 md:mt-48 flex flex-col items-center">
            <div className="flex flex-col md:flex-row items-center justify-center mb-16 scroll-reveal">
              <img src="/img/stars1.png" alt="stars" className="hidden md:block h-16 w-auto wave-animation" />
              <div className="text-center mx-2 md:mx-10 w-full md:w-1/2">
                <h1 className="font-poetsen text-darkest-purple text-4xl md:text-5xl font-light mb-2">Services</h1>
                <p className="font-poetsen text-black text-sm md:text-base font-light leading-relaxed">
                  CR8 provides rental spaces for creatives to showcase their work, along with opportunities for collaboration, workshops, and community events.
                </p>
              </div>
              <img src="/img/stars2.png" alt="stars" className="hidden md:block h-16 w-auto wave-animation" />
            </div>

            <div className="flex flex-col md:flex-row justify-center gap-8 md:gap-16 lg:gap-24 mb-12 md:mb-24">
              <div className="service-card flex flex-col items-center text-center w-full md:w-[340px] min-h-[300px] bg-white bg-opacity-50 rounded-xl p-6 scroll-reveal">
                <img src="/img/service1.svg" alt="Service 1" className="h-24 md:h-32 lg:h-36 transition duration-300" />
                <div className="flex flex-col flex-grow justify-center">
                  <h2 className="font-outfit text-purple text-xl md:text-2xl font-bold mt-4 mb-2">Provide Space for Creatives</h2>
                  <p className="font-poetsen text-black text-base font-light">
                    we offer shelves and racks for artists to showcase and sell their creations.
                  </p>
                </div>
              </div>
              <div className="service-card flex flex-col items-center text-center w-full md:w-[340px] min-h-[300px] bg-white bg-opacity-50 rounded-xl p-6 scroll-reveal">
                <img src="/img/service2.svg" alt="Service 2" className="h-24 md:h-32 lg:h-36 transition duration-300" />
                <div className="flex flex-col flex-grow justify-center">
                  <h2 className="font-outfit text-purple text-xl md:text-2xl font-bold mt-4 mb-2">Promote Products</h2>
                  <p className="font-poetsen text-black text-base font-light">
                    we market items through social media, in-house events, and other promotional efforts.
                  </p>
                </div>
              </div>
            </div>

            <div className="flex flex-col md:flex-row justify-center gap-8 md:gap-16 lg:gap-24">
              <div className="service-card flex flex-col items-center text-center w-full md:w-[340px] min-h-[300px] bg-white bg-opacity-50 rounded-xl p-6 scroll-reveal">
                <img src="/img/service3.svg" alt="Service 3" className="h-24 md:h-32 lg:h-36 transition duration-300" />
                <div className="flex flex-col flex-grow justify-center">
                  <h2 className="font-outfit text-purple text-xl md:text-2xl font-bold mt-4 mb-2">Manage Inventory And Sales</h2>
                  <p className="font-poetsen text-black text-base font-light">
                    we track inventory and sales, providing reports every Wednesday.
                  </p>
                </div>
              </div>
              <div className="service-card flex flex-col items-center text-center w-full md:w-[340px] min-h-[300px] bg-white bg-opacity-50 rounded-xl p-6 scroll-reveal">
                <img src="/img/service4.svg" alt="Service 4" className="h-24 md:h-32 lg:h-36 transition duration-300" />
                <div className="flex flex-col flex-grow justify-center">
                  <h2 className="font-outfit text-purple text-xl md:text-2xl font-bold mt-4 mb-2">Timely Sales Remittance</h2>
                  <p className="font-poetsen text-black text-base font-light">
                    sales are processed and remitted every Thursday.
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* About Section */}
          <div className="mt-32 md:mt-60 flex flex-col items-center">
            <div className="text-center w-full md:w-2/3 lg:w-1/2 lg:ml-36 scroll-reveal">
              <h1 className="font-poetsen text-darkest-purple text-4xl md:text-5xl font-light">About CR8</h1>
              <p className="font-poetsen text-black text-base md:text-lg font-light leading-relaxed mt-4">
                CR8 is an in-line mall rental space where creatives can set up shop and sell their work. It also serves as a vibrant community hub for meet-ups, group collaborations, activities, and workshops.
              </p>
            </div>

            <div className="text-center mt-24 md:mt-48 w-full lg:w-3/4 scroll-reveal">
              <h1 className="font-poetsen text-darkest-purple text-3xl md:text-4xl lg:text-5xl font-light">
                A Cozy Corner For Everyday Creatives
              </h1>
              <p className="font-poetsen text-black text-base md:text-lg lg:text-xl text-justify md:text-center mt-6 bg-pink-ish rounded-lg shadow-xl px-6 md:px-20 py-6 transform hover:scale-105 transition-transform duration-300">
                CR8 is more than just a marketplace—it's a dynamic space for creativity, collaboration, and growth. Beyond selling unique creations, it hosts vibrant events and workshops, a community where local artists and makers can showcase their unique creations.
              </p>
            </div>

            <img src="/img/squares.png" alt="Decoration" className="mt-16 md:mt-24 w-full max-w-lg animate-float scroll-reveal" />

            <div className="flex flex-col md:flex-row items-center gap-4 mt-16 md:mt-12 w-full md:w-2/3 scroll-reveal">
              <h1 className="font-lilita text-black text-8xl md:text-9xl animate-float">A</h1>
              <p className="font-lily text-2xl md:text-3xl lg:text-4xl text-center md:text-left">
                Community <span className="font-lilita">for</span> creatives <span className="font-lilita">and</span> crafters to connect and{' '}
                <span className="font-lilita">let</span> their collection <span className="font-lilita">take shape.</span>
              </p>
            </div>

            <img src="/img/squares.png" alt="Decoration" className="mt-16 md:mt-24 w-full max-w-lg animate-float scroll-reveal" />

            <div className="text-center mt-20 md:mt-32 w-full md:w-3/4 lg:w-2/3 scroll-reveal">
              <h1 className="font-lily text-black text-4xl md:text-5xl lg:text-6xl font-light">Creative Community</h1>
              <h2 className="font-lily text-black text-3xl md:text-4xl font-light mt-[-20px]">for Everyday Innovators</h2>
              <p className="font-poetsen text-black text-base md:text-lg lg:text-xl text-justify md:text-center mt-6 bg-pink-ish rounded-lg shadow-xl px-6 md:px-20 py-6 transform hover:scale-105 transition-transform duration-300">
                CR8 is more than just a marketplace—it's a dynamic space for creativity, collaboration, and growth. Beyond selling unique creations, it hosts vibrant events and workshops, bringing together curious minds and passionate souls in a thriving hub of inspiration.
              </p>
            </div>
          </div>
        </main>

        {/* Footer */}
        <footer className="relative z-10 mt-20 md:mt-32 bg-dark-purple w-full py-8 px-8">
          <div className="max-w-6xl mx-auto">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-6">
              <div className="text-center md:text-left">
                <h3 className="font-lilita text-pink-ish text-lg mb-3">CR8</h3>
                <p className="font-outfit text-bg-color text-sm leading-relaxed">
                  A cozy corner for everyday creatives to showcase their work and connect with the community.
                </p>
              </div>

              <div className="text-center">
                <h4 className="font-outfit font-bold text-bg-color text-base mb-3">Quick Links</h4>
                <div className="space-y-2">
                  <Link to="/shop" className="block font-outfit text-bg-color text-sm hover:text-pink-ish transition-colors">
                    Shop
                  </Link>
                  <Link to="/artist-application" className="block font-outfit text-bg-color text-sm hover:text-pink-ish transition-colors">
                    Become an Artist
                  </Link>
                </div>
              </div>

              <div className="text-center md:text-right">
                <h4 className="font-outfit font-bold text-bg-color text-base mb-3">Connect With Us</h4>
                <div className="space-y-2">
                  <p className="font-outfit text-bg-color text-sm">support@cr8.com</p>
                  <a
                    href="https://www.instagram.com/cr8.ceb/?hl=en"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block font-outfit text-bg-color text-sm hover:text-pink-ish transition-colors"
                  >
                    @cr8.ceb
                  </a>
                </div>
              </div>
            </div>

            <div className="border-t border-bg-color border-opacity-30 pt-6 text-center">
              <p className="font-outfit text-bg-color text-xs mb-2">© 2025 CR8. All Rights Reserved.</p>
              <div className="flex flex-wrap justify-center gap-4 text-xs">
                <a href="#" className="font-outfit text-bg-color hover:text-pink-ish transition-colors cursor-pointer">
                  Terms of Service
                </a>
                <a href="#" className="font-outfit text-bg-color hover:text-pink-ish transition-colors cursor-pointer">
                  Privacy Policy
                </a>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </div>
  )
}

export default Home
