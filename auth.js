function verification(req, res, next) {
  if (!req.session.user) {
    return res.status(401).json({ message: 'Unauthorised. Please login.' });
  }
      next();
  }

function verifyRole(role) {
  return (req, res, next) => {
    if (!req.session.user || req.session.user.role !== role) {
      return res.status(403).json({ message: 'Forbidden: Insufficient privileges.'});
    }
    next();
  };
}

module.exports = { verifyAuth, verifyRole };
    
