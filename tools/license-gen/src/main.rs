//! Vendor license issuer (run on your machine only).
//!
//! Flow:
//!   1. Customer opens the app → copies Machine ID
//!   2. You: cargo run -p license-gen -- issue --machine-id <ID> --seats 2 --customer "Name"
//!   3. Send them the FPOS1.… token
//!   4. They paste it in the app
//!
//! Issuance history (private): keys/issuance_log.json

use std::path::PathBuf;

use clap::{Parser, Subcommand};
use licensing::{issue, IssuanceLog};

#[derive(Parser)]
#[command(
    name = "license-gen",
    about = "Issue machine-bound FoodPOS offline licenses"
)]
struct Cli {
    /// Vendor-only issuance log (never ship to customers)
    #[arg(long, global = true, default_value = "keys/issuance_log.json")]
    log: PathBuf,

    #[command(subcommand)]
    command: Commands,
}

#[derive(Subcommand)]
enum Commands {
    /// Issue a license token for a customer's Machine ID
    Issue {
        #[arg(long)]
        machine_id: String,

        /// Max floor counters / seats
        #[arg(long, default_value_t = 1)]
        seats: u32,

        #[arg(long)]
        customer: Option<String>,
    },
    /// List previously issued licenses
    List,
}

fn main() {
    let cli = Cli::parse();
    if let Err(e) = run(cli) {
        eprintln!("error: {e}");
        std::process::exit(1);
    }
}

fn run(cli: Cli) -> Result<(), Box<dyn std::error::Error>> {
    let mut log = IssuanceLog::open(&cli.log)?;

    match cli.command {
        Commands::Issue {
            machine_id,
            seats,
            customer,
        } => {
            let issued = issue(&machine_id, seats, customer, None)?;
            log.append(&issued.claims, &issued.token)?;

            println!("Issued license");
            println!("  license_id: {}", issued.claims.license_id);
            println!("  machine_id: {}", issued.claims.machine_id);
            println!("  seats:      {}", issued.claims.seats);
            if let Some(c) = &issued.claims.customer {
                println!("  customer:   {c}");
            }
            println!("  log:        {}", log.path().display());
            println!();
            println!("Send this token to the customer:");
            println!("{}", issued.token);
        }
        Commands::List => {
            println!("Issuance log: {}", log.path().display());
            if log.records().is_empty() {
                println!("  (none)");
            }
            for r in log.records() {
                let customer = r.customer.as_deref().unwrap_or("-");
                println!(
                    "  {}  seats={}  customer={customer}  machine={}",
                    r.license_id, r.seats, r.machine_id
                );
            }
        }
    }

    Ok(())
}
