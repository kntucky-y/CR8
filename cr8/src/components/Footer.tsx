import { Link } from 'react-router-dom'

const Footer = () => {
  return (
    <footer className="bg-dark-purple text-white py-12 mt-16">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <img src="/img/cr8-logo.png" alt="CR8 Logo" className="w-20 mb-4" />
            <p className="text-sm text-gray-300">
              Supporting local artists and creative minds through a platform that showcases their unique products.
            </p>
          </div>
          
          <div>
            <h3 className="font-lilita text-xl mb-4">Quick Links</h3>
            <ul className="space-y-2">
              <li>
                <Link to="/shop" className="text-gray-300 hover:text-light-purple transition-colors">
                  Shop
                </Link>
              </li>
              <li>
                <Link to="/artist-application" className="text-gray-300 hover:text-light-purple transition-colors">
                  Become an Artist
                </Link>
              </li>
              <li>
                <Link to="/contact" className="text-gray-300 hover:text-light-purple transition-colors">
                  Contact Us
                </Link>
              </li>
            </ul>
          </div>
          
          <div>
            <h3 className="font-lilita text-xl mb-4">Contact</h3>
            <ul className="space-y-2 text-gray-300">
              <li>Email: support@cr8.com</li>
              <li>Phone: (123) 456-7890</li>
              <li>Location: Manila, Philippines</li>
            </ul>
          </div>
        </div>
        
        <div className="border-t border-gray-700 mt-8 pt-8 text-center text-sm text-gray-400">
          <p>&copy; {new Date().getFullYear()} CR8. All rights reserved.</p>
        </div>
      </div>
    </footer>
  )
}

export default Footer
