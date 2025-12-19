import { LayoutDashboard, Settings } from 'lucide-react'

const sidebarItems = [
  {
    id: "dashboard",
    label: "Dashboard",
    icon: LayoutDashboard,
    path: "/dashboard",
  },
  {
    id: "module1",
    label: "Real Property Tax Collection System",
    icon: LayoutDashboard,
    subItems: [
      { id: "rpt1", label: "RPT Configuration", path: "/RPT/RPTConfig" },
      { id: "rpt2", label: "RPT Validation", path: "/RPT/RPTValidationTable" },
      { id: "rpt3", label: "RPT Status", path: "/RPT/RPTStatus" },
      { id: "rpt4", label: "RPT Chart", path: "/RPT/RPTChart" },
    ]
  },
  {
    id: "module2",
    label: "Business Tax Collection Systems",
    icon: LayoutDashboard,
    subItems: [
      { id: "BusinessTaxConfig", label: "Business Tax Configuration", path: "/BUSINESS/BusinessTaxConfig" },
      { id: "BusinessValidation", label: "BusinessValidation", path: "/BUSINESS/BusinessValidation" },
    ]
  },
  {
    id: "module4",
    label: "Treasury Management System",
    icon: LayoutDashboard,
    subItems: [
      { id: "treasury1", label: "TREASURY1", path: "/TREASURY/TREASURY1" },
      { id: "treasury2", label: "TREASURY2", path: "/TREASURY/TREASURY2" },
    ]
  },
  {
    id: "module5",
    label: "Digital Payment System",
    icon: LayoutDashboard,
    subItems: [
      { id: "digipay1", label: "DIGIPAY1", path: "/DIGIPAY/DIGIPAY1" },
      { id: "digipay2", label: "DIGIPAY2", path: "/DIGIPAY/DIGIPAY2" },
    ]
  },
  {
    id: "module6",
    label: "Market Management System",
    icon: LayoutDashboard,
    subItems: [
      { id: "market1", label: "Market Stall Map Creator", path: "/MARKET/MapCreator" },
      { id: "market2", label: "MARKET2", path: "/MARKET/MARKET2" },
    ]
  },
  {
    id: "settings",
    label: "Settings",
    icon: Settings,
    subItems: [
      { id: "general-settings", label: "General", path: "/settings/general" },
      { id: "security-settings", label: "Security", path: "/settings/security" }
    ]
  }
]

export default sidebarItems
