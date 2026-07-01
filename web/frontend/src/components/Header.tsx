import logo from "../assets/logo.png";
import { Link } from "react-router-dom";
import { IoSettingsOutline } from "react-icons/io5";

export default function Header() {
  return (
    <header className="h-28 border-b bg-white relative flex items-center justify-center">
      <img src={logo} alt="Resmane" className="h-24 w-auto object-contain" />

      <Link
        to="/settings"
        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-600 hover:text-black transition-colors"
      >
        <IoSettingsOutline size={28} />
      </Link>
    </header>
  );
}